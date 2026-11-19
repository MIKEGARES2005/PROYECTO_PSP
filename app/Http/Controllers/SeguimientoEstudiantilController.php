<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionAcademica;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\HistorialSeguimientoEstudiante;
use App\Models\Inscripcion;
use App\Services\BitacoraService;
use App\Services\NotificacionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeguimientoEstudiantilController extends Controller
{
    private const EVENTOS = [
        'observacion',
        'congelamiento',
        'reincorporacion',
        'abandono',
    ];

    public function index(Request $request): View
    {
        $buscar = trim((string) $request->input('buscar'));
        $semestre = $request->input('semestre');
        $estudiantes = Estudiante::query()
            ->withCount('historialSeguimiento')
            ->when($buscar !== '', function ($query) use ($buscar): void {
                $query->where(function ($studentQuery) use ($buscar): void {
                    $studentQuery->where('nombres', 'like', "%{$buscar}%")
                        ->orWhere('apellido_paterno', 'like', "%{$buscar}%")
                        ->orWhere('apellido_materno', 'like', "%{$buscar}%")
                        ->orWhere('ci', 'like', "%{$buscar}%")
                        ->orWhere('matricula', 'like', "%{$buscar}%");
                });
            })
            ->when($semestre !== null && $semestre !== '', fn ($query) => $query->where('semestre', $semestre))
            ->orderBy('semestre')
            ->orderBy('apellido_paterno')
            ->orderBy('nombres')
            ->paginate(12)
            ->withQueryString();

        return view('seguimiento.index', [
            'estudiantes' => $estudiantes,
            'buscar' => $buscar,
            'semestre' => $semestre,
            'gestionVigente' => ConfiguracionAcademica::gestionVigente(),
            'totalActivos' => Estudiante::query()->where('estado', 'activo')->count(),
            'totalConSeguimiento' => Estudiante::query()->whereHas('historialSeguimiento')->count(),
            'totalPendientes' => Estudiante::query()->where('estado', 'activo')->whereDoesntHave('historialSeguimiento')->count(),
            'totalCongelados' => Estudiante::query()->where('estado', 'congelado')->count(),
            'totalEgresados' => Estudiante::query()->where('estado', 'egresado')->count(),
        ]);
    }

    public function show(Estudiante $estudiante): View
    {
        $estudiante->load([
            'user',
            'historialSeguimiento' => fn ($query) => $query->with(['registradoPor:id,username', 'gestion:id,codigo'])->latest('fecha_evento'),
            'inscripciones' => fn ($query) => $query->with([
                'materia:id,nombre,sigla,semestre',
                'materia.horarios' => fn ($horarios) => $horarios->with(['docente:id,nombres,apellido_paterno,apellido_materno', 'gestionAcademica:id,codigo']),
                'gestionAcademica:id,codigo',
            ])->orderByDesc('gestion'),
        ]);

        $inscripciones = $estudiante->inscripciones;

        return view('seguimiento.show', [
            'estudiante' => $estudiante,
            'inscripciones' => $inscripciones,
            'gestionVigente' => ConfiguracionAcademica::gestionVigente(),
            'resumen' => [
                'total' => $inscripciones->count(),
                'aprobadas' => $inscripciones->where('estado', 'Aprobado')->count(),
                'reprobadas' => $inscripciones->where('estado', 'Reprobado')->count(),
                'cursando' => $inscripciones->where('estado', 'Cursando')->count(),
            ],
        ]);
    }

    public function store(Request $request, Estudiante $estudiante): RedirectResponse
    {
        $datos = $request->validate([
            'tipo_evento' => ['required', Rule::in(self::EVENTOS)],
            'descripcion' => ['required_if:tipo_evento,observacion,congelamiento,abandono', 'nullable', 'string', 'max:1000'],
            'fecha_evento' => ['required', 'date'],
        ]);

        $tipoEvento = $datos['tipo_evento'];

        DB::transaction(function () use ($datos, &$estudiante, $tipoEvento, $request): void {
            $gestion = Gestion::query()->where('activa', true)->lockForUpdate()->first();
            $gestionVigente = ConfiguracionAcademica::gestionVigente();

            if ($gestion !== null
                && ConfiguracionAcademica::normalizarCodigo((string) $gestionVigente) !== $gestion->codigo) {
                throw ValidationException::withMessages([
                    'tipo_evento' => 'La gestión académica no está sincronizada. Actualiza la página o contacta con administración.',
                ]);
            }

            $estudiante = Estudiante::query()
                ->lockForUpdate()
                ->findOrFail($estudiante->id);

            if ($tipoEvento === 'congelamiento' && $estudiante->estado !== 'activo') {
                throw ValidationException::withMessages([
                    'tipo_evento' => 'Solo se puede congelar a un estudiante activo.',
                ]);
            }

            if ($tipoEvento === 'congelamiento'
                && $gestion !== null
                && $this->existenInscripcionesEnGestion($gestion, $estudiante->id)) {
                throw ValidationException::withMessages([
                    'tipo_evento' => 'No se puede congelar al estudiante mientras tenga inscripciones en la gestión vigente. Primero debe terminarse y cerrarse el periodo.',
                ]);
            }

            if ($tipoEvento === 'reincorporacion') {
                if ($estudiante->estado !== 'congelado') {
                    throw ValidationException::withMessages([
                        'tipo_evento' => 'La reincorporación solo aplica a estudiantes congelados.',
                    ]);
                }

                if ($gestion === null
                    || ConfiguracionAcademica::normalizarCodigo((string) $gestionVigente) !== $gestion->codigo) {
                    throw ValidationException::withMessages([
                        'tipo_evento' => 'La reincorporación solo se permite al inicio de una gestión activa, antes de generar inscripciones.',
                    ]);
                }

                if ($this->existenInscripcionesEnGestion($gestion)) {
                    throw ValidationException::withMessages([
                        'tipo_evento' => 'La gestión ya tiene inscripciones generadas. La reincorporación deberá registrarse al inicio de la siguiente gestión.',
                    ]);
                }
            }

            $gestionId = Gestion::query()->where('codigo', $gestionVigente)->value('id');

            HistorialSeguimientoEstudiante::create([
                'estudiante_id' => $estudiante->id,
                'gestion_id' => $gestionId,
                'tipo_evento' => $tipoEvento,
                'descripcion' => $datos['descripcion'] ?? null,
                'fecha_evento' => $datos['fecha_evento'],
                'registrado_por' => $request->user()->id,
            ]);

            $estadoAnterior = $estudiante->estado;

            if ($tipoEvento === 'congelamiento') {
                $estudiante->update(['estado' => 'congelado']);
                $estudiante->users()->update(['activo' => false]);
            }

            if ($tipoEvento === 'reincorporacion') {
                $estudiante->update(['estado' => 'activo']);
                $estudiante->users()->update(['activo' => true]);
            }

            if ($tipoEvento === 'abandono') {
                $estudiante->update(['estado' => 'retirado']);
                $estudiante->users()->get()->each->delete();
                $estudiante->delete();
            }

            BitacoraService::registrar(
                accion: 'Registrar evento de seguimiento estudiantil',
                entidad: 'estudiantes',
                entidadId: $estudiante->id,
                antes: ['estado' => $estadoAnterior, 'tipo_evento' => $tipoEvento],
                despues: ['estado' => $estudiante->estado, 'descripcion' => $datos['descripcion'] ?? null],
                actor: $request->user(),
            );

            $userId = $estudiante->users()->withTrashed()->value('id');

            if ($userId && in_array($tipoEvento, ['congelamiento', 'reincorporacion'], true)) {
                NotificacionService::notificarUsuario(
                    (int) $userId,
                    'seguimiento',
                    $tipoEvento === 'congelamiento' ? 'Cuenta congelada' : 'Cuenta reincorporada',
                    $datos['descripcion'] ?? 'Se actualizó tu estado en el seguimiento estudiantil.',
                    route('estudiante.dashboard'),
                );
            }
        });

        return redirect()
            ->route('seguimiento-estudiantil.index')
            ->with('success', "Evento de seguimiento registrado para {$estudiante->nombreCompleto()}.");
    }

    private function existenInscripcionesEnGestion(Gestion $gestion, ?int $estudianteId = null): bool
    {
        return Inscripcion::query()
            ->when($estudianteId !== null, fn (Builder $query) => $query->where('estudiante_id', $estudianteId))
            ->where(function (Builder $query) use ($gestion): void {
                $query->where('gestion_id', $gestion->id)
                    ->orWhere(function (Builder $legacyQuery) use ($gestion): void {
                        $legacyQuery->whereNull('gestion_id')
                            ->where('gestion', $gestion->codigo);
                    });
            })
            ->exists();
    }
}
