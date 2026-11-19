<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionAcademica;
use App\Models\Docente;
use App\Models\Gestion;
use App\Models\Horario;
use App\Models\Materia;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HorarioController extends Controller
{
    private const DIAS_SEMANA = [
        'Lunes',
        'Martes',
        'Miercoles',
        'Jueves',
        'Viernes',
    ];

    public function index(): RedirectResponse
    {
        return redirect()->route('gestion-academica.index');
    }

    public function create(Request $request): View
    {
        $gestionActiva = $this->gestionActiva();
        $semestre = $request->query('semestre');
        $horario = new Horario([
            'estado' => 'activo',
            'materia_id' => $request->query('materia_id'),
            'gestion' => $gestionActiva->codigo,
            'gestion_id' => $gestionActiva->id,
        ]);

        return view('academica.horarios.form', $this->formData($horario, $semestre, $gestionActiva->codigo));
    }

    public function store(Request $request): RedirectResponse
    {
        $gestionActiva = $this->gestionActiva();
        $datos = $this->validatedData($request);
        $horario = DB::transaction(function () use ($gestionActiva, $datos): Horario {
            $gestion = $this->bloquearGestionActiva($gestionActiva->id);

            return Horario::create(array_merge($datos, [
                'gestion' => $gestion->codigo,
                'gestion_id' => $gestion->id,
                'estado' => 'activo',
            ]));
        });

        return redirect()
            ->route('gestion-academica.semestre', [
                'semestre' => $horario->materia->semestre,
                'gestion' => $gestionActiva->codigo,
            ])
            ->with('success', 'Horario creado correctamente.');
    }

    public function edit(Request $request, Horario $horario): View
    {
        $this->validarHorarioActivo($horario);

        return view('academica.horarios.form', $this->formData(
            $horario,
            $horario->materia->semestre,
            $request->query('gestion'),
        ));
    }

    public function update(Request $request, Horario $horario): RedirectResponse
    {
        $gestionActiva = $this->gestionActiva();
        $datos = $this->validatedData($request);
        DB::transaction(function () use ($gestionActiva, $datos, $horario): void {
            $gestion = $this->bloquearGestionActiva($gestionActiva->id);
            $horarioBloqueado = Horario::query()->whereKey($horario->id)->lockForUpdate()->firstOrFail();
            $this->validarHorarioActivoParaGestion($horarioBloqueado, $gestion);
            $horarioBloqueado->update(array_merge($datos, [
                'gestion' => $gestion->codigo,
                'gestion_id' => $gestion->id,
            ]));
        });

        return redirect()
            ->route('gestion-academica.semestre', [
                'semestre' => $horario->materia->semestre,
                'gestion' => $gestionActiva->codigo,
            ])
            ->with('success', 'Horario actualizado correctamente.');
    }

    public function updateStatus(Request $request, Horario $horario): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ]);
        $gestionActiva = $this->gestionActiva();
        DB::transaction(function () use ($gestionActiva, $datos, $horario): void {
            $gestion = $this->bloquearGestionActiva($gestionActiva->id);
            $horarioBloqueado = Horario::query()->whereKey($horario->id)->lockForUpdate()->firstOrFail();
            $this->validarHorarioActivoParaGestion($horarioBloqueado, $gestion);
            $horarioBloqueado->update($datos);
        });

        return redirect()
            ->route('gestion-academica.materia', [
                'semestre' => $horario->materia->semestre,
                'materia' => $horario->materia,
                'gestion' => $gestionActiva->codigo,
            ])
            ->with('success', 'Estado del horario actualizado correctamente.');
    }

    private function formData(Horario $horario, ?string $semestre, ?string $gestion): array
    {
        $materias = Materia::query()
            ->orderBy('semestre')
            ->orderBy('nombre');

        if ($semestre !== null) {
            $materias->where('semestre', $semestre);
        }

        return [
            'horario' => $horario,
            'semestre' => $semestre,
            'gestion' => $gestion,
            'docentes' => Docente::query()
                ->orderBy('apellido_paterno')
                ->orderBy('nombres')
                ->get(),
            'materias' => $materias->get(),
            'diasSemana' => self::DIAS_SEMANA,
        ];
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'docente_id' => ['required', 'exists:docentes,id'],
            'materia_id' => ['required', 'exists:materias,id'],
            'dia_semana' => ['required', Rule::in(self::DIAS_SEMANA)],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'aula' => ['nullable', 'string', 'max:100'],
            'gestion' => ['nullable', 'string', 'regex:/^(?:\d{4}-(I|II)|(I|II)-\d{4})$/'],
        ]);
    }

    private function gestionActiva(): Gestion
    {
        $gestion = Gestion::activa();

        if ($gestion === null || ConfiguracionAcademica::gestionVigente() !== $gestion->codigo) {
            throw ValidationException::withMessages([
                'periodo' => 'No hay una gestión activa. Administración debe iniciar el periodo antes de crear horarios.',
            ]);
        }

        return $gestion;
    }

    private function validarHorarioActivo(Horario $horario): void
    {
        $gestion = $this->gestionActiva();

        $this->validarHorarioActivoParaGestion($horario, $gestion);
    }

    private function validarHorarioActivoParaGestion(Horario $horario, Gestion $gestion): void
    {
        $perteneceAGestion = $horario->gestion_id !== null
            ? (int) $horario->gestion_id === $gestion->id
            : ConfiguracionAcademica::normalizarCodigo((string) $horario->gestion) === $gestion->codigo;

        if (! $perteneceAGestion) {
            abort(404);
        }
    }

    private function bloquearGestionActiva(int $gestionId): Gestion
    {
        $configuracion = ConfiguracionAcademica::query()->whereKey(1)->lockForUpdate()->firstOrFail();
        $gestion = Gestion::query()->whereKey($gestionId)->lockForUpdate()->firstOrFail();

        if (ConfiguracionAcademica::normalizarCodigo((string) $configuracion->gestion_vigente) !== $gestion->codigo
            || ! $gestion->activa) {
            throw ValidationException::withMessages([
                'periodo' => 'La gestión dejó de estar activa. Actualiza la página antes de guardar el horario.',
            ]);
        }

        return $gestion;
    }
}
