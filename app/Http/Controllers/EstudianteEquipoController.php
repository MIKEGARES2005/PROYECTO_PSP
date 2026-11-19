<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\ConfiguracionAcademica;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Horario;
use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstudianteEquipoController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $student = $this->student($request);
        $gestion = $this->gestionActiva();

        if ($gestion === null) {
            return redirect()->route('estudiante.dashboard')->withErrors([
                'periodo' => 'No hay una gestión activa. Las solicitudes de equipo se habilitarán cuando comiencen las clases.',
            ]);
        }

        $horarios = Horario::query()
            ->with(['materia', 'docente'])
            ->where('estado', 'activo')
            ->whereHas('materia', fn (Builder $query) => $query->where('estado', 'activo'))
            ->whereHas('materia.inscripciones', function (Builder $query) use ($student, $gestion): void {
                $query->where('estudiante_id', $student->id)->whereRaw('LOWER(estado) = ?', ['cursando']);
                $this->whereGestion($query, 'inscripciones', $gestion);
            })
            ->where(function (Builder $query) use ($gestion): void {
                $this->whereGestion($query, 'horarios', $gestion);
            })
            ->orderBy('dia_semana')->orderBy('hora_inicio')->get();

        return view('estudiante.equipos.create', compact('student', 'horarios', 'gestion'));
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $gestion = $this->gestionActiva();

        if ($gestion === null) {
            return redirect()->route('estudiante.dashboard')->withErrors([
                'periodo' => 'No hay una gestión activa. Las solicitudes de equipo se habilitarán cuando comiencen las clases.',
            ]);
        }

        $data = $request->validate([
            'horario_id' => ['required', 'exists:horarios,id'],
            'tipo_equipo' => ['required', Rule::in(['cpu', 'tablet'])],
            'fecha_uso' => ['required', 'date', 'after_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
        $horario = Horario::with('materia')->whereKey($data['horario_id'])->where('estado', 'activo')->first();
        $inscrito = $horario && Inscripcion::query()
            ->where('estudiante_id', $student->id)->where('materia_id', $horario->materia_id)
            ->whereRaw('LOWER(estado) = ?', ['cursando'])
            ->where(fn (Builder $query) => $this->whereGestion($query, 'inscripciones', $gestion))->exists();
        $horarioVigente = $horario !== null && ($horario->gestion_id !== null
            ? (int) $horario->gestion_id === $gestion->id
            : ConfiguracionAcademica::normalizarCodigo((string) $horario->gestion) === $gestion->codigo);

        if (! $horarioVigente || ! $inscrito) {
            return back()->withInput()->withErrors(['horario_id' => 'El horario seleccionado no pertenece a una inscripción vigente.']);
        }
        $duplicada = Solicitud::query()->where('estudiante_solicitante_id', $student->id)->where('horario_id', $horario->id)
            ->whereDate('fecha_uso', $data['fecha_uso'])->whereIn('estado', ['pendiente', 'aprobada'])->exists();
        if ($duplicada) {
            return back()->withInput()->withErrors(['fecha_uso' => 'Ya tienes una solicitud vigente para ese horario y fecha.']);
        }

        $solicitud = Solicitud::create([
            'horario_id' => $horario->id,
            'estudiante_solicitante_id' => $student->id,
            'modalidad' => 'individual',
            'tipo_equipo' => $data['tipo_equipo'],
            'fecha_uso' => $data['fecha_uso'],
            'estado' => 'pendiente',
            'observaciones' => $data['observaciones'] ?? null,
        ]);
        $this->notifyApprovers($solicitud, $student);

        return redirect()->route('estudiante.loans.index')->with('success', 'Solicitud enviada. Queda pendiente de aprobación.');
    }

    public function loans(Request $request): View
    {
        $student = $this->student($request);
        $solicitudes = Solicitud::with(['horario.materia', 'horario.docente', 'equipo.activo', 'asignacion.equipo.activo'])
            ->where('estudiante_solicitante_id', $student->id)->latest()->get();
        $asignaciones = Asignacion::with(['equipo.activo', 'horario.materia', 'horario.docente', 'solicitud'])
            ->where('estudiante_id', $student->id)->latest()->get();

        return view('estudiante.equipos.loans', compact('student', 'solicitudes', 'asignaciones'));
    }

    public function requestReturn(Request $request, Asignacion $asignacion): RedirectResponse
    {
        $student = $this->student($request);
        abort_unless((int) $asignacion->estudiante_id === (int) $student->id, 403);
        $data = $request->validate(['motivo_devolucion' => ['required', 'string', 'max:1000']]);
        if ($asignacion->estado !== 'activa') {
            return back()->withErrors(['asignacion' => 'Esta asignación no está disponible para solicitar devolución.']);
        }
        $asignacion->update(['estado' => 'devolucion_pendiente', 'motivo_devolucion' => $data['motivo_devolucion']]);
        $this->notifyApprovers($asignacion->solicitud, $student, 'devolucion_equipo', 'Devolución de equipo pendiente', 'Un estudiante solicitó devolver un equipo.');

        return back()->with('success', 'La devolución fue solicitada y queda pendiente de autorización.');
    }

    public function report(Request $request, Asignacion $asignacion)
    {
        $student = $this->student($request);
        abort_unless((int) $asignacion->estudiante_id === (int) $student->id, 403);
        $asignacion->load(['equipo.activo', 'horario.materia', 'horario.docente', 'solicitud']);

        return Pdf::loadView('estudiante.equipos.report', compact('student', 'asignacion'))->setPaper('a4')->download('reporte-'.$asignacion->codigo.'.pdf');
    }

    private function student(Request $request): Estudiante
    {
        return $request->user('student')->load('estudiante')->estudiante;
    }

    private function whereGestion(Builder $query, string $table, Gestion $gestion): void
    {
        $query->where(function (Builder $gestionQuery) use ($table, $gestion): void {
            $gestionQuery->where("{$table}.gestion_id", $gestion->id)
                ->orWhere(function (Builder $legacyQuery) use ($table, $gestion): void {
                    $legacyQuery->whereNull("{$table}.gestion_id")
                        ->where("{$table}.gestion", $gestion->codigo);
                });
        });
    }

    private function gestionActiva(): ?Gestion
    {
        $gestion = Gestion::activa();

        if ($gestion === null || ConfiguracionAcademica::gestionVigente() !== $gestion->codigo) {
            return null;
        }

        return $gestion;
    }

    private function notifyApprovers(?Solicitud $solicitud, Estudiante $student, string $type = 'solicitud_equipo', string $title = 'Nueva solicitud de equipo', string $message = 'Un estudiante envió una solicitud de equipo.'): void
    {
        User::query()->where('activo', true)->whereIn('tipo_usuario', ['administrador', 'docente'])->get()->each(function (User $user) use ($student, $type, $title, $message): void {
            Notificacion::create(['user_id' => $user->id, 'tipo' => $type, 'titulo' => $title, 'mensaje' => $message.' Estudiante: '.$student->nombreCompleto(), 'url' => route('solicitudes.equipos.index')]);
        });
    }
}
