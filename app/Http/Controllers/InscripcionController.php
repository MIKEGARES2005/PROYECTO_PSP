<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionAcademica;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\User;
use App\Services\BitacoraService;
use App\Services\NotificacionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InscripcionController extends Controller
{
    public function index(Request $request): View
    {
        $gestionActiva = Gestion::activa();
        $materiasDisponibles = Materia::query()
            ->whereHas('inscripciones', function (Builder $query) use ($gestionActiva): void {
                $query->when(
                    $gestionActiva !== null,
                    function (Builder $inscripciones) use ($gestionActiva): void {
                        $inscripciones->where(function (Builder $gestionQuery) use ($gestionActiva): void {
                            $gestionQuery->where('gestion_id', $gestionActiva->id)
                                ->orWhere(function (Builder $legacyQuery) use ($gestionActiva): void {
                                    $legacyQuery->whereNull('gestion_id')
                                        ->where('gestion', $gestionActiva->codigo);
                                });
                        });
                    },
                    fn (Builder $inscripciones) => $inscripciones->whereRaw('1 = 0'),
                );
            })
            ->orderBy('semestre')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'semestre']);
        $filtros = $request->validate([
            'semestre' => ['nullable', 'string', Rule::in(array_values(Materia::SEMESTRES))],
            'materia_id' => ['nullable', 'integer', Rule::in($materiasDisponibles->modelKeys())],
        ]);
        if (isset($filtros['semestre'], $filtros['materia_id'])) {
            $materiaSeleccionada = $materiasDisponibles->firstWhere('id', $filtros['materia_id']);

            if (Materia::nombreSemestre($materiaSeleccionada?->semestre) !== $filtros['semestre']) {
                throw ValidationException::withMessages([
                    'materia_id' => 'Selecciona una materia que pertenezca al semestre elegido.',
                ]);
            }
        }

        $inscripciones = Inscripcion::query()
            ->with(['estudiante', 'materia'])
            ->when(
                $gestionActiva !== null,
                function (Builder $query) use ($gestionActiva): void {
                    $query->where(function (Builder $gestionQuery) use ($gestionActiva): void {
                        $gestionQuery->where('gestion_id', $gestionActiva->id)
                            ->orWhere(function (Builder $legacyQuery) use ($gestionActiva): void {
                                $legacyQuery->whereNull('gestion_id')
                                    ->where('gestion', $gestionActiva->codigo);
                            });
                    });
                },
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->when(isset($filtros['semestre']), function (Builder $query) use ($filtros): void {
                $numeroSemestre = array_search($filtros['semestre'], Materia::SEMESTRES, true);
                $valoresSemestre = [$filtros['semestre'], (string) $numeroSemestre];

                $query->whereHas(
                    'materia',
                    fn (Builder $materias) => $materias->whereIn('semestre', $valoresSemestre),
                );
            })
            ->when(
                isset($filtros['materia_id']),
                fn (Builder $query) => $query->where('materia_id', $filtros['materia_id']),
            )
            ->orderByDesc('fecha_inscripcion')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('academica.inscripciones.index', [
            'inscripciones' => $inscripciones,
            'gestionActiva' => $gestionActiva,
            'materiasDisponibles' => $materiasDisponibles,
            'materiasParaFiltro' => $materiasDisponibles
                ->map(fn (Materia $materia): array => [
                    'id' => $materia->id,
                    'nombre' => $materia->nombre,
                    'semestre' => $materia->semestre_nombre,
                ])
                ->values(),
            'filtros' => $filtros,
        ]);
    }

    public function create(Request $request): View
    {
        $gestion = $this->gestionActiva();
        $semestre = $request->query('semestre');
        abort_unless($semestre === null || in_array($semestre, Materia::SEMESTRES, true), 404);

        return view('academica.inscripciones.form', $this->formData(new Inscripcion([
            'estado' => 'Cursando',
            'fecha_inscripcion' => now()->toDateString(),
            'gestion' => $gestion->codigo,
            'gestion_id' => $gestion->id,
            'materia_id' => $request->query('materia_id'),
        ]), $semestre));
    }

    public function store(Request $request): RedirectResponse
    {
        $gestion = $this->gestionActiva();
        $request->merge(['gestion' => $gestion->codigo]);
        $datos = $this->validatedData($request);
        DB::transaction(function () use ($datos, $gestion): void {
            $gestionBloqueada = $this->bloquearGestionActiva($gestion->id);
            $this->validarElegibilidad(
                (int) $datos['estudiante_id'],
                (int) $datos['materia_id'],
                $gestionBloqueada,
            );
            Inscripcion::create($datos + [
                'gestion' => $gestionBloqueada->codigo,
                'gestion_id' => $gestionBloqueada->id,
                'estado' => 'Cursando',
            ]);
        });

        return $this->redirectToMateria((int) $datos['materia_id'], $datos['gestion'])
            ->with('success', 'Inscripción registrada correctamente.');
    }

    public function updateStatus(Request $request, Inscripcion $inscripcion): RedirectResponse
    {
        $this->validarInscripcionActiva($inscripcion);
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['Cursando', 'Aprobado', 'Reprobado'])],
        ]);
        $estadoAnterior = null;
        $antes = [];
        DB::transaction(function () use (&$inscripcion, &$estadoAnterior, &$antes, $datos, $request): void {
            $inscripcion = $this->bloquearInscripcionActiva($inscripcion);
            $this->ensureStudentCanContinue($inscripcion->estudiante_id, 'estado');

            if (in_array($datos['estado'], ['Aprobado', 'Reprobado'], true)
                && $inscripcion->resultado_reportado === null) {
                throw ValidationException::withMessages([
                    'estado' => 'El docente debe reportar el resultado antes de la revisión administrativa.',
                ]);
            }

            $estadoAnterior = $inscripcion->estado;
            $antes = $inscripcion->only(['estado', 'resultado_final', 'revisado_por', 'fecha_revision']);

            $inscripcion->update([
                'estado' => $datos['estado'],
                'resultado_final' => in_array($datos['estado'], ['Aprobado', 'Reprobado'], true)
                    ? $datos['estado']
                    : null,
                'revisado_por' => $request->user()->id,
                'fecha_revision' => now(),
            ]);

            if ($estadoAnterior !== $datos['estado']) {
                DB::table('decisiones_progresion')
                    ->where('gestion_id', $inscripcion->gestion_id)
                    ->where('estudiante_id', $inscripcion->estudiante_id)
                    ->update([
                        'decision_confirmada' => null,
                        'motivo' => null,
                        'revisado_por' => null,
                        'revisado_en' => null,
                        'updated_at' => now(),
                    ]);
            }
        });

        BitacoraService::registrar(
            'revisar_inscripcion',
            'inscripciones',
            $inscripcion->id,
            $antes,
            $inscripcion->only(['estado', 'resultado_final', 'revisado_por', 'fecha_revision']),
            $inscripcion->materia_id,
        );

        if ($estadoAnterior !== $inscripcion->estado
            && in_array($inscripcion->estado, ['Aprobado', 'Reprobado'], true)) {
            $this->notificarDecisionDocente($inscripcion);
        }

        return redirect()->route('gestion-academica.materia', [
            'semestre' => $inscripcion->materia->semestre,
            'materia' => $inscripcion->materia_id,
            'gestion' => $inscripcion->gestion,
        ])
            ->with('success', 'Decisión académica registrada correctamente.');
    }

    public function corregirNotas(Request $request, Inscripcion $inscripcion): RedirectResponse
    {
        $datos = $request->validate([
            'parcial_1' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'parcial_2' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'parcial_1.between' => 'La nota debe estar entre 0 y 100.',
            'parcial_2.between' => 'La nota debe estar entre 0 y 100.',
            'parcial_1.decimal' => 'La nota admite como máximo dos decimales.',
            'parcial_2.decimal' => 'La nota admite como máximo dos decimales.',
        ]);

        $antes = [];
        DB::transaction(function () use (&$inscripcion, &$antes, $datos, $request): void {
            $inscripcion = $this->bloquearInscripcionActiva($inscripcion);
            $this->ensureStudentCanContinue($inscripcion->estudiante_id, 'notas');

            if ($inscripcion->resultado_reportado === null) {
                throw ValidationException::withMessages([
                    'notas' => 'Solo se pueden corregir notas después de que el docente reporte los resultados.',
                ]);
            }

            if ((float) $inscripcion->parcial_1 === (float) $datos['parcial_1']
                && (float) $inscripcion->parcial_2 === (float) $datos['parcial_2']) {
                throw ValidationException::withMessages([
                    'notas' => 'Ingresa al menos una nota diferente para registrar una corrección.',
                ]);
            }

            $antes = $inscripcion->only([
                'parcial_1',
                'parcial_2',
                'nota_final',
                'resultado_reportado',
                'resultado_final',
                'estado',
                'revisado_por',
                'fecha_revision',
            ]);

            $inscripcion->parcial_1 = (float) $datos['parcial_1'];
            $inscripcion->parcial_2 = (float) $datos['parcial_2'];
            $inscripcion->nota_final = $inscripcion->calcularNotaFinal();
            $inscripcion->nota_final_en = now();
            $inscripcion->resultado_reportado = $inscripcion->resultadoSegunNotaFinal();
            $inscripcion->estado = 'Cursando';
            $inscripcion->resultado_final = null;
            $inscripcion->revisado_por = null;
            $inscripcion->fecha_revision = null;
            $inscripcion->save();

            DB::table('decisiones_progresion')
                ->where('gestion_id', $inscripcion->gestion_id)
                ->where('estudiante_id', $inscripcion->estudiante_id)
                ->update([
                    'decision_confirmada' => null,
                    'motivo' => null,
                    'revisado_por' => null,
                    'revisado_en' => null,
                    'updated_at' => now(),
                ]);

            $despues = $inscripcion->only([
                'parcial_1',
                'parcial_2',
                'nota_final',
                'resultado_reportado',
                'resultado_final',
                'estado',
                'revisado_por',
                'fecha_revision',
            ]);
            $despues['motivo'] = $datos['motivo'];

            BitacoraService::registrar(
                'corregir_notas_administracion',
                'inscripciones',
                $inscripcion->id,
                $antes,
                $despues,
                $inscripcion->materia_id,
                $request->user(),
            );
        });

        return redirect()->route('gestion-academica.materia', [
            'semestre' => $inscripcion->materia->semestre,
            'materia' => $inscripcion->materia_id,
            'gestion' => $inscripcion->gestion,
        ])->with('success', 'Notas corregidas. El resultado volvió a quedar pendiente de revisión administrativa.');
    }

    public function registerPartial(Request $request, Inscripcion $inscripcion): RedirectResponse
    {
        $this->validarInscripcionActiva($inscripcion);
        $datos = $request->validate([
            'parcial' => ['required', Rule::in(['1', '2'])],
            'nota' => ['required', 'numeric', 'between:0,100'],
        ], [
            'nota.between' => 'La nota debe estar entre 0 y 100.',
            'nota.numeric' => 'La nota debe ser un número entre 0 y 100.',
        ]);

        $numero = (int) $datos['parcial'];
        $asignacion = DB::transaction(function () use ($request, $inscripcion, $datos, $numero): Inscripcion {
            $asignacion = $this->assignedEnrollment(
                $request,
                $this->bloquearInscripcionActiva($inscripcion),
            );
            $this->ensureStudentCanContinue($asignacion->estudiante_id, 'nota');

            if ($asignacion->estado !== 'Cursando') {
                throw ValidationException::withMessages([
                    'nota' => 'Solo se pueden registrar notas de inscripciones que estén cursando.',
                ]);
            }

            if ($asignacion->resultado_reportado !== null) {
                throw ValidationException::withMessages([
                    'nota' => 'Las notas no se pueden modificar después de enviar el resultado a administración.',
                ]);
            }

            if ($numero === 2 && $asignacion->parcial_1 === null) {
                throw ValidationException::withMessages([
                    'nota' => 'Primero debes registrar la nota del primer parcial.',
                ]);
            }

            $antes = $asignacion->only([
                'parcial_1',
                'parcial_1_por',
                'parcial_1_en',
                'parcial_2',
                'parcial_2_por',
                'parcial_2_en',
                'nota_final',
                'nota_final_en',
            ]);

            $atributos = $numero === 1
                ? [
                    'parcial_1' => (float) $datos['nota'],
                    'parcial_1_por' => $request->user()->id,
                    'parcial_1_en' => now(),
                ]
                : [
                    'parcial_2' => (float) $datos['nota'],
                    'parcial_2_por' => $request->user()->id,
                    'parcial_2_en' => now(),
                ];

            $asignacion->fill($atributos);

            if ($asignacion->tieneAmbosParciales()) {
                $asignacion->nota_final = $asignacion->calcularNotaFinal();
                $asignacion->nota_final_en = now();
            }

            $asignacion->save();
            BitacoraService::registrar(
                "registrar_parcial_{$numero}",
                'inscripciones',
                $asignacion->id,
                $antes,
                $asignacion->only(array_keys($antes)),
                $asignacion->materia_id,
            );

            return $asignacion;
        });

        $mensaje = $asignacion->nota_final !== null
            ? 'Segundo parcial registrado. Nota final calculada: '.number_format((float) $asignacion->nota_final, 2).'.'
            : 'Primer parcial registrado correctamente.';

        return back()->with('success', $mensaje);
    }

    public function reportResults(Request $request, Materia $materia): RedirectResponse
    {
        $gestion = $this->gestionActiva();
        $reporte = DB::transaction(function () use ($request, $materia, $gestion): array {
            $gestion = $this->bloquearGestionActiva($gestion->id);
            $materia = Materia::query()
                ->whereKey($materia->id)
                ->whereHas('horarios', function (Builder $horarios) use ($request, $gestion): void {
                    $this->filtrarHorarioDocente($horarios, (int) $request->user()->docente_id, $gestion);
                })
                ->firstOrFail();
            $inscripciones = Inscripcion::query()
                ->with('estudiante')
                ->where('materia_id', $materia->id)
                ->where('estado', 'Cursando')
                ->where(function (Builder $query) use ($gestion): void {
                    $query->where('gestion_id', $gestion->id)
                        ->orWhere(function (Builder $legacyQuery) use ($gestion): void {
                            $legacyQuery->whereNull('gestion_id')
                                ->where('gestion', $gestion->codigo);
                        });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($inscripciones->isEmpty()) {
                throw ValidationException::withMessages([
                    'resultados' => 'No hay estudiantes cursando en esta materia durante la gestión vigente.',
                ]);
            }

            $incompletas = $inscripciones->filter(
                fn (Inscripcion $inscripcion): bool => ! $inscripcion->tieneAmbosParciales(),
            );

            if ($incompletas->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'resultados' => 'No se pueden enviar resultados hasta que todos los estudiantes cursando tengan ambos parciales.',
                ]);
            }

            $pendientes = $inscripciones->whereNull('resultado_reportado');

            if ($pendientes->isEmpty()) {
                throw ValidationException::withMessages([
                    'resultados' => 'Los resultados de esta materia ya fueron enviados a administración.',
                ]);
            }

            foreach ($pendientes as $inscripcion) {
                $this->ensureStudentCanContinue($inscripcion->estudiante_id, 'resultados');
                $notaFinal = $inscripcion->calcularNotaFinal();
                $resultado = $inscripcion->resultadoSegunNotaFinal();
                $antes = $inscripcion->only([
                    'nota_final',
                    'nota_final_en',
                    'resultado_reportado',
                    'resultado_reportado_por',
                    'fecha_reporte',
                ]);
                $inscripcion->update([
                    'nota_final' => $notaFinal,
                    'nota_final_en' => $inscripcion->nota_final_en ?? now(),
                    'resultado_reportado' => $resultado,
                    'resultado_reportado_por' => $request->user()->id,
                    'fecha_reporte' => now(),
                ]);
                BitacoraService::registrar(
                    'reportar_resultado',
                    'inscripciones',
                    $inscripcion->id,
                    $antes,
                    $inscripcion->only(array_keys($antes)),
                    $materia->id,
                );
            }

            return [
                'materia' => $materia,
                'cantidad' => $pendientes->count(),
                'gestion' => $gestion->codigo,
            ];
        });

        NotificacionService::notificarAdmins(
            'resultados_materia_reportados',
            'Resultados de materia listos para revisión',
            "Se reportaron {$reporte['cantidad']} resultados de {$reporte['materia']->nombre} para la gestión {$reporte['gestion']}.",
            route('gestion-academica.materia', [
                'semestre' => $reporte['materia']->semestre,
                'materia' => $reporte['materia'],
                'gestion' => $reporte['gestion'],
            ]),
        );

        return back()->with('success', 'Los resultados completos de la materia fueron enviados a administración para su revisión.');
    }

    private function assignedEnrollment(Request $request, Inscripcion $inscripcion): Inscripcion
    {
        $gestion = Gestion::activa();

        if ($gestion === null) {
            throw ValidationException::withMessages([
                'periodo' => 'No hay una gestión activa para registrar calificaciones.',
            ]);
        }

        return Inscripcion::query()
            ->whereKey($inscripcion->id)
            ->whereHas('materia.horarios', function (Builder $horarios) use ($request, $gestion): void {
                $this->filtrarHorarioDocente($horarios, (int) $request->user()->docente_id, $gestion);
            })
            ->firstOrFail();
    }

    private function filtrarHorarioDocente(Builder $horarios, int $docenteId, Gestion $gestion): void
    {
        $horarios->where('docente_id', $docenteId)
            ->whereRaw('LOWER(estado) = ?', ['activo'])
            ->where(function (Builder $query) use ($gestion): void {
                $query->where('gestion_id', $gestion->id)
                    ->orWhere(function (Builder $legacyQuery) use ($gestion): void {
                        $legacyQuery->whereNull('gestion_id')
                            ->where('gestion', $gestion->codigo);
                    });
            });
    }

    private function gestionActiva(): Gestion
    {
        $gestion = Gestion::activa();

        if ($gestion === null || ConfiguracionAcademica::gestionVigente() !== $gestion->codigo) {
            throw ValidationException::withMessages([
                'periodo' => 'No hay una gestión activa. Administración debe iniciar el periodo antes de realizar operaciones académicas.',
            ]);
        }

        return $gestion;
    }

    private function bloquearGestionActiva(int $gestionId): Gestion
    {
        $configuracion = ConfiguracionAcademica::query()->whereKey(1)->lockForUpdate()->firstOrFail();
        $gestion = Gestion::query()->whereKey($gestionId)->lockForUpdate()->firstOrFail();

        if (ConfiguracionAcademica::normalizarCodigo((string) $configuracion->gestion_vigente) !== $gestion->codigo
            || ! $gestion->activa) {
            throw ValidationException::withMessages([
                'periodo' => 'La gestión dejó de estar activa. Actualiza la página antes de realizar cambios académicos.',
            ]);
        }

        return $gestion;
    }

    private function validarInscripcionActiva(Inscripcion $inscripcion): void
    {
        $this->validarInscripcionParaGestion($inscripcion, $this->gestionActiva());
    }

    private function bloquearInscripcionActiva(Inscripcion $inscripcion): Inscripcion
    {
        $gestion = Gestion::activa();

        if ($gestion === null) {
            throw ValidationException::withMessages([
                'periodo' => 'La gestión dejó de estar activa. Actualiza la página e inténtalo nuevamente.',
            ]);
        }

        $gestion = $this->bloquearGestionActiva($gestion->id);
        $inscripcion = Inscripcion::query()->whereKey($inscripcion->id)->lockForUpdate()->firstOrFail();
        $this->validarInscripcionParaGestion($inscripcion, $gestion);

        return $inscripcion;
    }

    private function validarInscripcionParaGestion(Inscripcion $inscripcion, Gestion $gestion): void
    {
        $perteneceAGestion = $inscripcion->gestion_id !== null
            ? (int) $inscripcion->gestion_id === $gestion->id
            : ConfiguracionAcademica::normalizarCodigo((string) $inscripcion->gestion) === $gestion->codigo;

        if (! $perteneceAGestion) {
            abort(404);
        }
    }

    private function notificarDecisionDocente(Inscripcion $inscripcion): void
    {
        $docenteId = $inscripcion->materia?->horarios()->value('docente_id');

        if ($docenteId === null) {
            return;
        }

        $docente = User::query()
            ->where('docente_id', $docenteId)
            ->where('tipo_usuario', 'docente')
            ->first();

        if ($docente === null) {
            return;
        }

        NotificacionService::notificarUsuario(
            $docente->id,
            'decision_academica',
            'Decisión académica registrada',
            "La inscripción de {$inscripcion->estudiante?->nombres} {$inscripcion->estudiante?->apellido_paterno} en {$inscripcion->materia?->nombre} fue marcada como {$inscripcion->estado}.",
            route('panel-docente.gestion-academica.materia', [
                'semestre' => $inscripcion->materia?->semestre,
                'materia' => $inscripcion->materia_id,
                'gestion' => $inscripcion->gestion,
            ]),
        );
    }

    private function formData(Inscripcion $inscripcion, ?string $semestre): array
    {
        $materias = Materia::query()->orderBy('semestre')->orderBy('nombre');
        $numeroSemestre = $semestre === null
            ? null
            : array_search($semestre, Materia::SEMESTRES, true);
        if ($semestre !== null) {
            $materias->where('semestre', $semestre);
        }

        return [
            'inscripcion' => $inscripcion,
            'semestre' => $semestre,
            'estudiantes' => Estudiante::query()
                ->where('estado', 'activo')
                ->when($semestre !== null, function (Builder $query) use ($numeroSemestre, $semestre): void {
                    $valoresSemestre = [$semestre];

                    if ($numeroSemestre !== false) {
                        $valoresSemestre[] = (string) $numeroSemestre;
                    }

                    $query->whereIn('semestre', $valoresSemestre);
                })
                ->orderBy('semestre')
                ->orderBy('apellido_paterno')
                ->orderBy('nombres')
                ->get(),
            'materias' => $materias->get(),
        ];
    }

    private function validatedData(Request $request): array
    {
        $estudianteId = $request->input('estudiante_id');
        $materiaId = $request->input('materia_id');
        $gestion = ConfiguracionAcademica::normalizarCodigo((string) $request->input('gestion'));

        $reglas = [
            'estudiante_id' => ['required', 'exists:estudiantes,id'],
            'materia_id' => [
                'required',
                'exists:materias,id',
                Rule::unique('inscripciones')
                    ->where(fn (QueryBuilder $query) => $query
                        ->where('estudiante_id', $estudianteId)
                        ->where('materia_id', $materiaId)
                        ->where('gestion', $gestion)),
            ],
            'gestion' => ['required', 'regex:/^(?:\d{4}-(I|II)|(I|II)-\d{4})$/'],
            'tipo' => ['required', Rule::in(['Normal', 'Arrastre'])],
            'fecha_inscripcion' => ['required', 'date'],
        ];

        $datos = $request->validate($reglas, [
            'materia_id.unique' => 'El estudiante ya tiene una inscripción registrada en esta materia durante esta gestión.',
        ], [
            'gestion' => 'gestión académica',
        ]);

        $datos['gestion'] = ConfiguracionAcademica::normalizarCodigo($datos['gestion']);

        return $datos;
    }

    private function redirectToMateria(int $materiaId, string $gestion): RedirectResponse
    {
        $materia = Materia::findOrFail($materiaId);

        return redirect()->route('gestion-academica.materia', [
            'semestre' => $materia->semestre,
            'materia' => $materia->id,
            'gestion' => $gestion,
        ]);
    }

    private function ensureStudentCanContinue(int $studentId, string $field = 'estudiante_id'): void
    {
        $estudiante = Estudiante::withTrashed()
            ->whereKey($studentId)
            ->first();

        if ($estudiante === null || $estudiante->trashed() || strtolower((string) $estudiante->estado) !== 'activo') {
            throw ValidationException::withMessages([
                $field => 'El estudiante no está activo; sus registros académicos permanecen pausados hasta su reincorporación.',
            ]);
        }
    }

    private function validarElegibilidad(int $estudianteId, int $materiaId, Gestion $gestion): void
    {
        $this->ensureStudentCanContinue($estudianteId);

        $estudiante = Estudiante::query()
            ->whereKey($estudianteId)
            ->lockForUpdate()
            ->first();

        if ($estudiante === null || strtolower((string) $estudiante->estado) !== 'activo') {
            throw ValidationException::withMessages([
                'estudiante_id' => 'El estudiante debe estar activo para inscribirse.',
            ]);
        }

        $materia = Materia::query()
            ->whereKey($materiaId)
            ->lockForUpdate()
            ->first();

        if ($materia === null || ! in_array(strtolower((string) $materia->estado), ['activo', 'activa'], true)) {
            throw ValidationException::withMessages([
                'materia_id' => 'La materia debe estar activa para registrar la inscripción.',
            ]);
        }

        $semestreEstudiante = Materia::nombreSemestre($estudiante->semestre);
        $semestreMateria = Materia::nombreSemestre($materia->semestre);

        if ($semestreEstudiante === null || $semestreEstudiante !== $semestreMateria) {
            throw ValidationException::withMessages([
                'materia_id' => 'El semestre de la materia debe coincidir con el semestre del estudiante.',
            ]);
        }

        $tieneHorarioActivo = $materia->horarios()
            ->whereRaw('LOWER(estado) = ?', ['activo'])
            ->where(function (Builder $query) use ($gestion): void {
                $query->where('gestion_id', $gestion->id)
                    ->orWhere(function (Builder $legacyQuery) use ($gestion): void {
                        $legacyQuery->whereNull('gestion_id')
                            ->where('gestion', $gestion->codigo);
                    });
            })
            ->exists();

        if (! $tieneHorarioActivo) {
            throw ValidationException::withMessages([
                'materia_id' => 'La materia debe tener un horario activo en la gestión vigente para inscribir estudiantes.',
            ]);
        }
    }
}
