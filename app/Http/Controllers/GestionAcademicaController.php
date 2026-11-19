<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionAcademica;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\HistorialSeguimientoEstudiante;
use App\Models\Horario;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Services\BitacoraService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GestionAcademicaController extends Controller
{
    // ==========================================
    // 1. GESTIONES ACADÉMICAS
    // ==========================================
    public function indexGestiones(Request $request): View
    {
        $gestion = ConfiguracionAcademica::gestionVigente();
        $gestionActiva = Gestion::activa();
        $user = $request->user();
        $gestionId = $gestionActiva?->id;

        $semestres = collect(['Primero', 'Segundo', 'Tercero', 'Cuarto'])
            ->map(function (string $nombre) use ($user, $gestion, $gestionId): array {
                $materias = Materia::query()
                    ->where('semestre', $nombre)
                    ->whereHas('horarios', function (Builder $query) use ($gestion, $gestionId): void {
                        $this->filtrarGestion($query, 'horarios', $gestion, $gestionId);
                    });
                $horarios = Horario::query()
                    ->whereHas('materia', fn (Builder $query) => $query->where('semestre', $nombre))
                    ->when($gestion !== null, function (Builder $query) use ($gestion, $gestionId): void {
                        $this->filtrarGestion($query, 'horarios', $gestion, $gestionId);
                    }, fn (Builder $query) => $query->whereRaw('1 = 0'));
                $inscripciones = Inscripcion::query()
                    ->when($gestion !== null, function (Builder $query) use ($gestion, $gestionId): void {
                        $this->filtrarGestion($query, 'inscripciones', $gestion, $gestionId);
                    }, fn (Builder $query) => $query->whereRaw('1 = 0'))
                    ->whereHas('materia', fn (Builder $query) => $query->where('semestre', $nombre));

                if ($user?->isDocente() && ! $user->isAdministrador()) {
                    $materias->whereHas(
                        'horarios',
                        fn (Builder $query) => $query->where('docente_id', $user->docente_id),
                    );
                    $horarios->where('docente_id', $user->docente_id);
                    $inscripciones->whereHas(
                        'materia.horarios',
                        fn (Builder $query) => $query->where('docente_id', $user->docente_id),
                    );
                }

                return [
                    'nombre' => $nombre,
                    'materias' => (clone $materias)->count(),
                    'horarios' => $horarios->count(),
                    'inscripciones' => $inscripciones->count(),
                ];
            });

        return view('academica.index', [
            'gestion' => $gestion,
            'esAdministrador' => $user?->isAdministrador() ?? false,
            'vistaPreviaInscripciones' => ($user?->isAdministrador() ?? false)
                && $gestionActiva !== null
                && $gestion === $gestionActiva->codigo
                    ? $this->planificarInscripciones($gestionActiva)
                    : null,
            'semestres' => $semestres,
            'cierre' => $gestion !== null ? $this->resumenCierre($gestion, $gestionId) : null,
            'gestionesArchivadas' => DB::table('inscripciones_archivadas')
                ->select('gestion')
                ->distinct()
                ->orderByDesc('gestion')
                ->get(),
        ]);
    }

    public function semestre(Request $request, string $semestre): View
    {
        abort_unless(in_array($semestre, Materia::SEMESTRES, true), 404);

        $user = $request->user();
        [$gestion, $gestionVigente, $gestionSeleccionada] = $this->periodoSeleccionado($request);
        $esDocente = $user->isDocente() && ! $user->isAdministrador();
        $materias = Materia::query()
            ->where('semestre', $semestre)
            ->when($esDocente, function (Builder $query) use ($user, $gestion, $gestionSeleccionada): void {
                $query->whereRaw('LOWER(estado) = ?', ['activo'])
                    ->whereHas('horarios', function (Builder $horarios) use ($user, $gestion, $gestionSeleccionada): void {
                        $horarios->where('docente_id', $user->docente_id)
                            ->whereRaw('LOWER(estado) = ?', ['activo']);
                        $this->filtrarGestion($horarios, 'horarios', $gestion, $gestionSeleccionada?->id);
                    });
            })
            ->withCount([
                'horarios' => function (Builder $query) use ($gestion, $gestionSeleccionada, $esDocente, $user): void {
                    $this->filtrarGestion($query, 'horarios', $gestion, $gestionSeleccionada?->id);
                    if ($esDocente) {
                        $query->where('docente_id', $user->docente_id);
                    }
                },
                'inscripciones' => function (Builder $query) use ($gestion, $gestionSeleccionada): void {
                    $this->filtrarGestion($query, 'inscripciones', $gestion, $gestionSeleccionada?->id);
                },
            ])
            ->orderBy('nombre')
            ->get();

        return view('academica.semestre', [
            'semestre' => $semestre,
            'gestion' => $gestion,
            'gestionVigente' => $gestionVigente,
            'materias' => $materias,
            'esDocente' => $esDocente,
            'esAdministrador' => $user->isAdministrador(),
            'gestiones' => $esDocente ? collect([$gestion])->filter()->values() : $this->gestionesDisponibles(),
            'puedeCrearMateria' => $user->isAdministrador() && $gestion !== null && $gestion === $gestionVigente,
        ]);
    }

    public function materia(Request $request, string $semestre, Materia $materia): View
    {
        abort_unless(in_array($semestre, Materia::SEMESTRES, true) && $materia->semestre === $semestre, 404);

        $user = $request->user();
        [$gestion, $gestionVigente, $gestionSeleccionada] = $this->periodoSeleccionado($request);
        $esDocente = $user->isDocente() && ! $user->isAdministrador();
        $consulta = Materia::query()->whereKey($materia->id);

        if ($esDocente) {
            $consulta->whereRaw('LOWER(estado) = ?', ['activo'])
                ->whereHas('horarios', function (Builder $horarios) use ($user, $gestion, $gestionSeleccionada): void {
                    $horarios->where('docente_id', $user->docente_id)
                        ->whereRaw('LOWER(estado) = ?', ['activo']);
                    $this->filtrarGestion($horarios, 'horarios', $gestion, $gestionSeleccionada?->id);
                });
        }

        $materia = $consulta
            ->with([
                'horarios' => function (Relation $query) use ($user, $esDocente, $gestion, $gestionSeleccionada): void {
                    $this->filtrarGestion($query, 'horarios', $gestion, $gestionSeleccionada?->id);
                    if ($esDocente) {
                        $query->where('docente_id', $user->docente_id);
                    }
                    $query->orderBy('dia_semana')->orderBy('hora_inicio');
                },
                'horarios.docente',
                'inscripciones' => fn (Relation $query) => $this->filtrarGestion(
                    $query->with(['estudiante', 'reportadoPor', 'revisadoPor', 'primerParcialPor', 'segundoParcialPor'])
                        ->orderBy('estudiante_id'),
                    'inscripciones',
                    $gestion,
                    $gestionSeleccionada?->id,
                ),
            ])
            ->firstOrFail();

        $puedeEditarPeriodo = $user->isAdministrador()
            && $gestion !== null
            && $gestion === $gestionVigente;
        $inscripcionesCursando = $materia->inscripciones->where('estado', 'Cursando');
        $faltanCalificaciones = $inscripcionesCursando->filter(
            fn (Inscripcion $inscripcion): bool => ! $inscripcion->tieneAmbosParciales(),
        )->count();
        $hayResultadosPendientes = $inscripcionesCursando->contains(
            fn (Inscripcion $inscripcion): bool => $inscripcion->resultado_reportado === null,
        );
        $puedeReportarResultados = $esDocente
            && $gestion !== null
            && $gestion === $gestionVigente
            && $inscripcionesCursando->isNotEmpty()
            && $faltanCalificaciones === 0
            && $hayResultadosPendientes;
        $puedeCalificarComoDocente = $user->isDocente()
            && $user->docente_id !== null
            && $gestion !== null
            && $gestion === $gestionVigente
            && $materia->horarios->contains(
                fn (Horario $horario): bool => (int) $horario->docente_id === (int) $user->docente_id
                    && strtolower($horario->estado) === 'activo',
            );
        $esAdministrador = $user->isAdministrador();
        $gestionesDisponibles = $this->gestionesDisponibles();

        return view('academica.materia', compact(
            'semestre',
            'gestion',
            'gestionVigente',
            'materia',
            'esDocente',
            'esAdministrador',
            'gestionesDisponibles',
            'puedeEditarPeriodo',
            'puedeReportarResultados',
            'puedeCalificarComoDocente',
            'faltanCalificaciones',
            'inscripcionesCursando',
        ));
    }

    public function iniciarGestion(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2024', 'max:2050'],
            'periodo' => ['required', Rule::in(['1', '2'])],
        ]);

        $codigo = ($datos['periodo'] === '1' ? 'I' : 'II').'-'.(int) $datos['anio'];

        DB::transaction(function () use ($codigo, $request): void {
            ConfiguracionAcademica::query()->findOrFail(1, ['id', 'gestion_vigente']);

            if (Gestion::activa() !== null) {
                throw ValidationException::withMessages([
                    'periodo' => 'Primero debe concluirse la gestión vigente antes de iniciar otra.',
                ]);
            }

            if (Gestion::query()->where('codigo', $codigo)->exists()) {
                throw ValidationException::withMessages([
                    'periodo' => 'Esa gestión ya existe. No se puede iniciar nuevamente un periodo cerrado.',
                ]);
            }

            $gestion = Gestion::query()->create([
                'codigo' => $codigo,
                ...$this->atributosGestion($codigo),
                'activa' => true,
            ]);

            ConfiguracionAcademica::actualizarGestionVigente($codigo);
            BitacoraService::registrar(
                'iniciar_gestion_academica',
                'gestiones',
                $gestion->id,
                ['estado' => 'en_espera'],
                $gestion->toArray(),
                actor: $request->user(),
            );
        });

        return redirect()->route('gestion-academica.index')
            ->with('success', "La gestión {$codigo} fue iniciada. Prepara los horarios y luego genera las inscripciones desde Gestión Académica.");
    }

    private function bloquearGestionVigente(): Gestion
    {
        $configuracion = ConfiguracionAcademica::query()->whereKey(1)->lockForUpdate()->firstOrFail();
        $gestion = Gestion::query()->where('activa', true)->lockForUpdate()->first();

        if ($gestion === null
            || ConfiguracionAcademica::normalizarCodigo((string) $configuracion->gestion_vigente) !== $gestion->codigo) {
            throw ValidationException::withMessages([
                'periodo' => 'No hay una gestión activa. Administración debe iniciar el periodo antes de generar inscripciones.',
            ]);
        }

        return $gestion;
    }

    public function generarInscripciones(Request $request): RedirectResponse
    {
        $resultado = DB::transaction(function () use ($request): array {
            $gestion = $this->bloquearGestionVigente();
            $plan = $this->planificarInscripciones($gestion, true);
            $estudiantes = $plan['estudiantes'];

            if ($estudiantes->isEmpty()) {
                throw ValidationException::withMessages([
                    'generacion' => 'No hay estudiantes activos disponibles para generar inscripciones.',
                ]);
            }

            if ($plan['estudiantes_semestre_invalido']->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'generacion' => 'Hay estudiantes sin un semestre válido: '.$plan['estudiantes_semestre_invalido']->implode(', ').'.',
                ]);
            }

            if ($plan['semestres_sin_materias']->isNotEmpty()) {
                $semestre = $plan['semestres_sin_materias']->first();

                throw ValidationException::withMessages([
                    'generacion' => "No hay materias activas configuradas para {$semestre} semestre.",
                ]);
            }

            if ($plan['materias_faltantes']->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'generacion' => 'No se puede generar: hay materias pendientes que ya no están disponibles en el catálogo.',
                ]);
            }

            if ($plan['materias_inactivas']->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'generacion' => 'Activa estas materias pendientes antes de generar: '.$plan['materias_inactivas']->pluck('nombre')->implode(', ').'.',
                ]);
            }

            if ($plan['materias_sin_horario']->isNotEmpty()) {
                $nombres = $plan['materias_requeridas']
                    ->only($plan['materias_sin_horario']->all())
                    ->pluck('nombre')
                    ->implode(', ');
                throw ValidationException::withMessages([
                    'generacion' => 'Programa un horario activo en la gestión vigente para estas materias antes de generar inscripciones: '.$nombres.'.',
                ]);
            }

            $existentes = $plan['existentes'];
            $creadas = ['Normal' => 0, 'Arrastre' => 0];
            $fechaInscripcion = now()->toDateString();

            foreach ($plan['candidatas'] as $inscripcionesEstudiante) {
                foreach ($inscripcionesEstudiante as $inscripcion) {
                    $clave = $inscripcion['estudiante_id'].':'.$inscripcion['materia_id'];

                    if ($existentes->has($clave)) {
                        continue;
                    }

                    Inscripcion::create($inscripcion + [
                        'gestion_id' => $gestion->id,
                        'gestion' => $gestion->codigo,
                        'estado' => 'Cursando',
                        'fecha_inscripcion' => $fechaInscripcion,
                    ]);
                    $creadas[$inscripcion['tipo']]++;
                }
            }

            $totalCreadas = array_sum($creadas);
            if ($totalCreadas > 0) {
                BitacoraService::registrar(
                    'generar_inscripciones_gestion',
                    'gestiones',
                    $gestion->id,
                    ['inscripciones_existentes' => $existentes->count()],
                    ['inscripciones_creadas' => $totalCreadas, 'tipos' => $creadas],
                    actor: $request->user(),
                );
            }

            return [
                'creadas' => $totalCreadas,
                'existentes' => $existentes->count(),
                'normales' => $creadas['Normal'],
                'arrastres' => $creadas['Arrastre'],
            ];
        });

        if ($resultado['creadas'] === 0) {
            return redirect()->route('gestion-academica.index')
                ->with('success', 'No se crearon inscripciones nuevas: las materias requeridas ya estaban inscritas.');
        }

        return redirect()->route('gestion-academica.index')
            ->with('success', "Se generaron {$resultado['creadas']} inscripciones: {$resultado['normales']} normales y {$resultado['arrastres']} arrastres. Se omitieron {$resultado['existentes']} que ya existían.");
    }

    /**
     * @return array{
     *     estudiantes: Collection<int, Estudiante>,
     *     estudiantes_semestre_invalido: Collection<int, int>,
     *     semestres_sin_materias: Collection<int, string>,
     *     candidatas: array<int, array<int, array{estudiante_id: int, materia_id: int, tipo: string}>>,
     *     materias_requeridas: Collection<int, Materia>,
     *     materias_faltantes: Collection<int, int>,
     *     materias_inactivas: Collection<int, Materia>,
     *     materias_sin_horario: Collection<int, int>,
     *     existentes: Collection<string, bool>,
     *     resumen: array<int, array{nombre: string, estudiantes: array<int, array{nombre: string, matricula: string, materias: array<int, array{nombre: string, semestre: string, tipo: string}>}>}>,
     *     total_estudiantes: int,
     *     total_nuevas: int,
     *     total_existentes: int,
     *     nuevas_normales: int,
     *     nuevos_arrastres: int,
     *     advertencias: array<int, string>,
     *     todos_ya_inscritos: bool,
     *     puede_generar: bool
     * }
     */
    private function planificarInscripciones(Gestion $gestion, bool $bloquearEstudiantes = false): array
    {
        $estudiantes = Estudiante::query()
            ->whereRaw('LOWER(estado) = ?', ['activo'])
            ->orderBy('id')
            ->when($bloquearEstudiantes, fn (Builder $query) => $query->lockForUpdate())
            ->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'matricula', 'semestre']);

        $semestres = $estudiantes
            ->map(fn (Estudiante $estudiante): ?string => Materia::nombreSemestre($estudiante->semestre))
            ->filter()
            ->unique()
            ->values();
        $valoresSemestre = $semestres->flatMap(function (string $semestre): array {
            $numero = array_search($semestre, Materia::SEMESTRES, true);

            return [$semestre, (string) $numero];
        })->unique()->values();
        $materiasRegulares = Materia::query()
            ->whereRaw('LOWER(estado) = ?', ['activo'])
            ->whereIn('semestre', $valoresSemestre)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Materia $materia): ?string => Materia::nombreSemestre($materia->semestre));

        $historialReciente = DB::table('inscripciones_archivadas')
            ->whereIn('estudiante_id', $estudiantes->modelKeys())
            ->orderByDesc('archivado_en')
            ->orderByDesc('id')
            ->get(['estudiante_id', 'materia_id', 'decision_progresion'])
            ->unique(fn (object $registro): string => $registro->estudiante_id.':'.$registro->materia_id)
            ->where('decision_progresion', 'arrastre')
            ->groupBy('estudiante_id');

        $candidatas = [];
        $estudiantesSemestreInvalido = collect();
        $semestresSinMaterias = collect();

        foreach ($estudiantes as $estudiante) {
            $semestre = Materia::nombreSemestre($estudiante->semestre);

            if ($semestre === null) {
                $estudiantesSemestreInvalido->push($estudiante->id);

                continue;
            }

            $materiasDelSemestre = $materiasRegulares->get($semestre, collect());

            if ($materiasDelSemestre->isEmpty()) {
                $semestresSinMaterias[$estudiante->id] = $semestre;
            }

            foreach ($materiasDelSemestre as $materia) {
                $candidatas[$estudiante->id][$materia->id] = [
                    'estudiante_id' => $estudiante->id,
                    'materia_id' => $materia->id,
                    'tipo' => 'Normal',
                ];
            }

            foreach ($historialReciente->get($estudiante->id, collect()) as $registro) {
                if (! isset($candidatas[$estudiante->id][$registro->materia_id])) {
                    $candidatas[$estudiante->id][$registro->materia_id] = [
                        'estudiante_id' => $estudiante->id,
                        'materia_id' => (int) $registro->materia_id,
                        'tipo' => 'Arrastre',
                    ];
                }
            }
        }

        $estudianteIds = collect(array_keys($candidatas));
        $todasLasMateriasIds = collect($candidatas)
            ->flatMap(fn (array $materias): array => array_keys($materias))
            ->unique()
            ->values();
        $existentes = Inscripcion::query()
            ->where(function (Builder $query) use ($gestion): void {
                $query->where('gestion_id', $gestion->id)
                    ->orWhere(function (Builder $legacyQuery) use ($gestion): void {
                        $legacyQuery->whereNull('gestion_id')
                            ->where('gestion', $gestion->codigo);
                    });
            })
            ->whereIn('estudiante_id', $estudianteIds)
            ->whereIn('materia_id', $todasLasMateriasIds)
            ->get(['estudiante_id', 'materia_id'])
            ->mapWithKeys(fn (Inscripcion $inscripcion): array => [
                $inscripcion->estudiante_id.':'.$inscripcion->materia_id => true,
            ]);

        $candidatasNuevas = collect($candidatas)
            ->map(fn (array $materias): array => collect($materias)
                ->reject(fn (array $candidata): bool => $existentes->has($candidata['estudiante_id'].':'.$candidata['materia_id']))
                ->all())
            ->filter(fn (array $materias): bool => $materias !== [])
            ->all();
        $estudiantesPendientes = $estudiantes->filter(
            fn (Estudiante $estudiante): bool => isset($candidatasNuevas[$estudiante->id]),
        );
        $semestresSinMaterias = $semestresSinMaterias
            ->filter(fn (string $semestre, int|string $estudianteId): bool => isset($candidatasNuevas[$estudianteId]))
            ->unique()
            ->values();
        $materiaIds = collect($candidatasNuevas)
            ->flatMap(fn (array $materias): array => array_column($materias, 'materia_id'))
            ->unique()
            ->values();
        $materiasRequeridas = Materia::query()->whereIn('id', $materiaIds)->get()->keyBy('id');
        $materiasFaltantes = $materiaIds->diff($materiasRequeridas->keys());
        $materiasInactivas = $materiasRequeridas
            ->filter(fn (Materia $materia): bool => strtolower((string) $materia->estado) !== 'activo');
        $horariosActivos = Horario::query()
            ->whereIn('materia_id', $materiaIds)
            ->whereRaw('LOWER(estado) = ?', ['activo'])
            ->where(function (Builder $query) use ($gestion): void {
                $query->where('gestion_id', $gestion->id)
                    ->orWhere(function (Builder $legacyQuery) use ($gestion): void {
                        $legacyQuery->whereNull('gestion_id')
                            ->where('gestion', $gestion->codigo);
                    });
            })
            ->when($bloquearEstudiantes, fn (Builder $query) => $query->lockForUpdate())
            ->pluck('materia_id')
            ->unique();
        $materiasSinHorario = $materiaIds->diff($horariosActivos);

        $resumen = $estudiantesPendientes
            ->groupBy(fn (Estudiante $estudiante): string => Materia::nombreSemestre($estudiante->semestre) ?? 'Semestre no válido')
            ->map(fn (Collection $estudiantesSemestre): array => [
                'nombre' => $estudiantesSemestre->first()->semestre_nombre,
                'estudiantes' => $estudiantesSemestre->map(function (Estudiante $estudiante) use ($candidatasNuevas, $materiasRequeridas): array {
                    $materias = collect($candidatasNuevas[$estudiante->id] ?? [])
                        ->map(function (array $candidata) use ($materiasRequeridas): array {
                            $materia = $materiasRequeridas->get($candidata['materia_id']);

                            return [
                                'nombre' => $materia?->nombre ?? 'Materia no disponible',
                                'semestre' => $materia?->semestre_nombre ?? 'Semestre no disponible',
                                'tipo' => $candidata['tipo'],
                            ];
                        })
                        ->values()
                        ->all();

                    return [
                        'nombre' => $estudiante->nombreCompleto(),
                        'matricula' => (string) $estudiante->matricula,
                        'materias' => $materias,
                    ];
                })->all(),
            ])
            ->sortBy(fn (array $grupo): int => array_search($grupo['nombre'], Materia::SEMESTRES, true) ?: 99)
            ->values();
        $totalNuevas = 0;
        $nuevasNormales = 0;
        $nuevosArrastres = 0;

        foreach ($candidatasNuevas as $inscripcionesEstudiante) {
            foreach ($inscripcionesEstudiante as $candidata) {
                $totalNuevas++;
                if ($candidata['tipo'] === 'Normal') {
                    $nuevasNormales++;
                } else {
                    $nuevosArrastres++;
                }
            }
        }

        $advertencias = [];
        if ($estudiantes->isEmpty()) {
            $advertencias[] = 'No hay estudiantes activos disponibles para generar inscripciones.';
        }
        if ($estudiantesSemestreInvalido->isNotEmpty()) {
            $advertencias[] = 'Hay estudiantes sin un semestre válido: '.$estudiantesSemestreInvalido->implode(', ').'.';
        }
        if ($semestresSinMaterias->isNotEmpty()) {
            $advertencias[] = 'No hay materias activas configuradas para: '.$semestresSinMaterias->unique()->implode(', ').' semestre.';
        }
        if ($materiasFaltantes->isNotEmpty()) {
            $advertencias[] = 'Hay materias de arrastre que ya no están disponibles en el catálogo.';
        }
        if ($materiasInactivas->isNotEmpty()) {
            $advertencias[] = 'Activa estas materias pendientes antes de generar: '.$materiasInactivas->pluck('nombre')->implode(', ').'.';
        }
        if ($materiasSinHorario->isNotEmpty()) {
            $nombres = $materiasRequeridas->only($materiasSinHorario->all())->pluck('nombre')->implode(', ');
            $advertencias[] = 'Programa un horario activo en la gestión vigente para estas materias: '.$nombres.'.';
        }
        $todosYaInscritos = $estudiantes->isNotEmpty()
            && $totalNuevas === 0
            && $advertencias === [];

        return [
            'estudiantes' => $estudiantes,
            'estudiantes_semestre_invalido' => $estudiantesSemestreInvalido,
            'semestres_sin_materias' => $semestresSinMaterias->unique()->values(),
            'candidatas' => $candidatasNuevas,
            'materias_requeridas' => $materiasRequeridas,
            'materias_faltantes' => $materiasFaltantes,
            'materias_inactivas' => $materiasInactivas,
            'materias_sin_horario' => $materiasSinHorario,
            'existentes' => $existentes,
            'resumen' => $resumen->all(),
            'total_estudiantes' => $estudiantesPendientes->count(),
            'total_nuevas' => $totalNuevas,
            'total_existentes' => $existentes->count(),
            'nuevas_normales' => $nuevasNormales,
            'nuevos_arrastres' => $nuevosArrastres,
            'advertencias' => $advertencias,
            'todos_ya_inscritos' => $todosYaInscritos,
            'puede_generar' => $estudiantesPendientes->isNotEmpty() && $totalNuevas > 0 && $advertencias === [],
        ];
    }

    public function progresion(): View
    {
        $gestion = Gestion::activa();
        abort_if($gestion === null || ConfiguracionAcademica::gestionVigente() !== $gestion->codigo, 404);
        $inscripciones = Inscripcion::query()
            ->with(['estudiante' => fn ($query) => $query->withTrashed(), 'materia'])
            ->where('gestion_id', $gestion->id)
            ->orderBy('estudiante_id')
            ->orderBy('materia_id')
            ->get();
        $inscripcionesPendientes = $inscripciones->filter(
            fn (Inscripcion $inscripcion): bool => ! in_array($inscripcion->estado, ['Aprobado', 'Reprobado'], true)
                || ! in_array($inscripcion->resultado_final, ['Aprobado', 'Reprobado'], true),
        )->count();
        $recomendaciones = $inscripcionesPendientes === 0
            ? $this->decisionesProgresion($inscripciones)
            : [];
        $estudianteIds = array_keys($recomendaciones);
        $decisionesGuardadas = $estudianteIds === []
            ? collect()
            : DB::table('decisiones_progresion')
                ->where('gestion_id', $gestion->id)
                ->whereIn('estudiante_id', $estudianteIds)
                ->get()
                ->keyBy('estudiante_id');
        $progresiones = collect($recomendaciones)->map(function (array $recomendacion, int $estudianteId) use ($decisionesGuardadas, $inscripciones): array {
            $guardada = $decisionesGuardadas->get($estudianteId);

            return [
                'estudiante' => $recomendacion['estudiante'],
                'recomendacion' => $recomendacion['decision'],
                'decision_confirmada' => $guardada?->decision_recomendada === $recomendacion['decision']
                    ? $guardada->decision_confirmada
                    : null,
                'motivo' => $guardada?->motivo,
                'opciones' => $this->opcionesDecisionProgresion($recomendacion),
                'materias_aprobadas' => $recomendacion['materias_aprobadas'],
                'materias_reprobadas' => $recomendacion['materias_reprobadas'],
                'inscripciones' => $inscripciones->where('estudiante_id', $estudianteId)->values(),
            ];
        })->values();
        $decisionesPendientes = $progresiones->filter(
            fn (array $progresion): bool => $progresion['decision_confirmada'] === null,
        )->count();

        return view('academica.progresion', [
            'gestion' => $gestion,
            'progresiones' => $progresiones,
            'inscripcionesPendientes' => $inscripcionesPendientes,
            'decisionesPendientes' => $decisionesPendientes,
        ]);
    }

    public function confirmarProgresion(Request $request, Estudiante $estudiante): RedirectResponse
    {
        $datos = $request->validate([
            'decision' => ['required', 'string', 'max:40'],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $estudiante, $datos): void {
            $gestion = $this->bloquearGestionVigente();
            $estudiante = Estudiante::query()->whereKey($estudiante->id)->lockForUpdate()->firstOrFail();
            $inscripciones = Inscripcion::query()
                ->with(['estudiante', 'materia'])
                ->where('gestion_id', $gestion->id)
                ->where('estudiante_id', $estudiante->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($inscripciones->isEmpty()) {
                abort(404);
            }

            $tieneResultadosPendientes = $inscripciones->contains(
                fn (Inscripcion $inscripcion): bool => ! in_array($inscripcion->estado, ['Aprobado', 'Reprobado'], true)
                    || ! in_array($inscripcion->resultado_final, ['Aprobado', 'Reprobado'], true),
            );

            if ($tieneResultadosPendientes) {
                throw ValidationException::withMessages([
                    'decision' => 'Primero deben revisarse todos los resultados de este estudiante.',
                ]);
            }

            $recomendacion = $this->decisionesProgresion($inscripciones)[$estudiante->id] ?? null;

            if ($recomendacion === null) {
                throw ValidationException::withMessages([
                    'decision' => 'No se pudo calcular una recomendación de progresión para este estudiante.',
                ]);
            }

            if (! in_array($datos['decision'], $this->opcionesDecisionProgresion($recomendacion), true)) {
                throw ValidationException::withMessages([
                    'decision' => 'La decisión seleccionada no es válida para el semestre y los resultados de este estudiante.',
                ]);
            }

            $motivo = trim((string) ($datos['motivo'] ?? ''));

            if ($datos['decision'] !== $recomendacion['decision'] && mb_strlen($motivo) < 10) {
                throw ValidationException::withMessages([
                    'motivo' => 'Explica el motivo para cambiar la recomendación (mínimo 10 caracteres).',
                ]);
            }

            $ahora = now();
            $anterior = DB::table('decisiones_progresion')
                ->where('gestion_id', $gestion->id)
                ->where('estudiante_id', $estudiante->id)
                ->lockForUpdate()
                ->first();
            $decisionAnterior = $anterior?->decision_confirmada;
            $datosDecision = [
                'decision_recomendada' => $recomendacion['decision'],
                'decision_confirmada' => $datos['decision'],
                'motivo' => $motivo !== '' ? $motivo : null,
                'revisado_por' => $request->user()->id,
                'revisado_en' => $ahora,
                'updated_at' => $ahora,
                'created_at' => $anterior?->created_at ?? $ahora,
            ];

            DB::table('decisiones_progresion')->updateOrInsert([
                'gestion_id' => $gestion->id,
                'estudiante_id' => $estudiante->id,
            ], $datosDecision);

            BitacoraService::registrar(
                'confirmar_progresion_estudiantil',
                'decisiones_progresion',
                (int) ($anterior?->id ?? DB::table('decisiones_progresion')
                    ->where('gestion_id', $gestion->id)
                    ->where('estudiante_id', $estudiante->id)
                    ->value('id')),
                ['decision_confirmada' => $decisionAnterior],
                $datosDecision,
                actor: $request->user(),
            );
        });

        return redirect()->route('gestion-academica.progresion')
            ->with('success', 'La decisión de progresión quedó confirmada y auditada.');
    }

    public function cerrarGestion(Request $request): RedirectResponse
    {
        $gestionActual = ConfiguracionAcademica::gestionVigente();
        $gestion = Gestion::query()->where('activa', true)->first();

        if ($gestionActual === null || $gestion === null || $gestion->codigo !== $gestionActual) {
            throw ValidationException::withMessages([
                'cierre' => 'No hay una gestión académica activa para cerrar.',
            ]);
        }

        $cierre = $this->resumenCierre($gestionActual, $gestion->id);

        if (! $cierre['todos_revisados']) {
            throw ValidationException::withMessages([
                'cierre' => $this->mensajeCierrePendiente($cierre),
            ]);
        }

        DB::transaction(function () use ($gestion, $gestionActual, $request): void {
            $configuracion = ConfiguracionAcademica::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $gestionBloqueada = Gestion::query()->whereKey($gestion->id)->lockForUpdate()->firstOrFail();

            if (ConfiguracionAcademica::normalizarCodigo((string) $configuracion->gestion_vigente) !== $gestionActual
                || ! $gestionBloqueada->activa
                || $gestionBloqueada->codigo !== $gestionActual) {
                throw ValidationException::withMessages([
                    'cierre' => 'La gestión dejó de estar activa. Actualiza la página antes de cerrarla.',
                ]);
            }

            $inscripciones = Inscripcion::query()
                ->with([
                    'estudiante' => fn ($query) => $query->withTrashed(),
                    'materia',
                ])
                ->where(function (Builder $query) use ($gestionBloqueada, $gestionActual): void {
                    $query->where('gestion_id', $gestionBloqueada->id)
                        ->orWhere(function (Builder $legacyQuery) use ($gestionActual): void {
                            $legacyQuery->whereNull('gestion_id')
                                ->where('gestion', $gestionActual);
                        });
                })
                ->lockForUpdate()
                ->get();

            if ($inscripciones->contains(
                fn (Inscripcion $inscripcion): bool => ! in_array($inscripcion->estado, ['Aprobado', 'Reprobado'], true)
                    || ! in_array($inscripcion->resultado_final, ['Aprobado', 'Reprobado'], true),
            )) {
                throw ValidationException::withMessages([
                    'cierre' => 'No se puede cerrar: todos los estudiantes deben tener un resultado final de aprobado o reprobado.',
                ]);
            }

            $recomendaciones = $this->decisionesProgresion($inscripciones);
            $decisionesGuardadas = $recomendaciones === []
                ? collect()
                : DB::table('decisiones_progresion')
                    ->where('gestion_id', $gestionBloqueada->id)
                    ->whereIn('estudiante_id', array_keys($recomendaciones))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('estudiante_id');
            $decisiones = [];

            foreach ($recomendaciones as $estudianteId => $recomendacion) {
                $decisionGuardada = $decisionesGuardadas->get($estudianteId);

                if ($decisionGuardada === null
                    || $decisionGuardada->decision_confirmada === null
                    || $decisionGuardada->decision_recomendada !== $recomendacion['decision']
                    || ! in_array(
                        $decisionGuardada->decision_confirmada,
                        $this->opcionesDecisionProgresion($recomendacion),
                        true,
                    )) {
                    throw ValidationException::withMessages([
                        'cierre' => 'No se puede cerrar: confirma nuevamente las decisiones de progresión pendientes o desactualizadas.',
                    ]);
                }

                $decisionConfirmada = $decisionGuardada->decision_confirmada;
                $recomendacion['decision_confirmada'] = $decisionConfirmada;
                $recomendacion['nuevo_semestre'] = in_array($decisionConfirmada, ['avanza', 'avanza_con_arrastres'], true)
                    ? $this->nombreSemestre($this->numeroSemestre($recomendacion['estudiante']?->semestre) + 1)
                    : null;
                $recomendacion['nuevo_estado'] = $decisionConfirmada === 'retiro' ? 'egresado' : null;

                foreach ($recomendacion['inscripciones'] as $inscripcion) {
                    $recomendacion['por_inscripcion'][$inscripcion->id] = $decisionConfirmada === 'avanza_con_arrastres'
                        && $inscripcion->resultado_final === 'Reprobado'
                        ? 'arrastre'
                        : $decisionConfirmada;
                }

                $decisiones[$estudianteId] = $recomendacion;
            }

            $ahora = now();

            foreach ($inscripciones as $inscripcion) {
                $estudiante = $inscripcion->estudiante;
                $materia = $inscripcion->materia;

                DB::table('inscripciones_archivadas')->insert([
                    'gestion' => $gestionActual,
                    'inscripcion_original_id' => $inscripcion->id,
                    'estudiante_id' => $inscripcion->estudiante_id,
                    'estudiante_nombre' => $estudiante?->nombreCompleto() ?? 'Estudiante no disponible',
                    'matricula' => $estudiante?->matricula,
                    'materia_id' => $inscripcion->materia_id,
                    'materia_nombre' => $materia?->nombre ?? 'Materia no disponible',
                    'semestre' => (string) ($materia?->semestre ?? ''),
                    'tipo' => (string) $inscripcion->tipo,
                    'resultado_final' => (string) $inscripcion->resultado_final,
                    'nota_final' => $inscripcion->nota_final,
                    'decision_progresion' => $decisiones[$inscripcion->estudiante_id]['por_inscripcion'][$inscripcion->id],
                    'datos_inscripcion' => json_encode($inscripcion->getAttributes(), JSON_THROW_ON_ERROR),
                    'archivado_en' => $ahora,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }

            foreach ($decisiones as $decision) {
                if ($decision['estudiante'] === null) {
                    continue;
                }

                $atributosEstudiante = [];

                if ($decision['nuevo_semestre'] !== null) {
                    $atributosEstudiante['semestre'] = $decision['nuevo_semestre'];
                }

                if ($decision['nuevo_estado'] !== null) {
                    $atributosEstudiante['estado'] = $decision['nuevo_estado'];
                }

                if ($atributosEstudiante !== []) {
                    $decision['estudiante']->update($atributosEstudiante);
                }

                if ($decision['nuevo_estado'] === 'egresado') {
                    HistorialSeguimientoEstudiante::create([
                        'estudiante_id' => $decision['estudiante']->id,
                        'gestion_id' => $gestionBloqueada->id,
                        'tipo_evento' => 'egreso',
                        'descripcion' => 'Egreso académico confirmado después de aprobar todas las materias de cuarto semestre.',
                        'fecha_evento' => $ahora->toDateString(),
                        'registrado_por' => $request->user()->id,
                    ]);

                    $decision['estudiante']->users()->update(['activo' => false]);
                }
            }

            Inscripcion::query()->whereIn('id', $inscripciones->modelKeys())->delete();
            $gestionBloqueada->update(['activa' => false]);
            BitacoraService::registrar(
                'cerrar_gestion_academica',
                'gestiones',
                $gestionBloqueada->id,
                ['gestion' => $gestionActual, 'inscripciones_activas' => $inscripciones->count()],
                [
                    'gestion' => $gestionActual,
                    'inscripciones_archivadas' => $inscripciones->count(),
                    'estado' => 'en_espera',
                ],
                actor: $request->user(),
            );
        });

        return redirect()
            ->route('gestion-academica.index')
            ->with('success', "La gestión {$gestionActual} fue archivada y cerrada. El sistema quedó en espera hasta que administración inicie el siguiente periodo.");
    }

    public function historial(string $gestion): View
    {
        abort_unless(preg_match('/^(I|II)-\d{4}$/', $gestion) === 1, 404);

        $inscripciones = DB::table('inscripciones_archivadas')
            ->where('gestion', $gestion)
            ->orderBy('estudiante_nombre')
            ->orderBy('materia_nombre')
            ->paginate(25);

        abort_if($inscripciones->total() === 0, 404);

        return view('academica.historial', compact('gestion', 'inscripciones'));
    }

    // ==========================================
    // 2. MATERIAS
    // ==========================================
    public function indexMaterias(Request $request): View
    {
        $buscar = $request->input('buscar');
        $materias = Materia::query()
            ->when($buscar, fn ($q) => $q->where('nombre', 'like', "%{$buscar}%")->orWhere('sigla', 'like', "%{$buscar}%"))
            ->orderBy('semestre')
            ->orderBy('nombre')
            ->paginate(15);

        return view('admin. academic.materias.index', compact('materias', 'buscar'));
    }

    public function storeMateria(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'sigla' => ['nullable', 'string', 'max:20', Rule::unique('materias', 'sigla')],
            'semestre' => ['required', 'integer', 'between:1,4'],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ]);

        $materia = Materia::create($datos);
        BitacoraService::registrar('crear_materia', 'materias', $materia->id, null, $materia->toArray());

        return redirect()->route('admin.materias.index')->with('success', 'Materia registrada correctamente.');
    }

    // ==========================================
    // 3. HORARIOS
    // ==========================================
    public function indexHorarios(Request $request): View
    {
        $gestionActiva = Gestion::activa();
        $horarios = Horario::with(['gestionAcademica', 'docente', 'materia'])
            ->when($gestionActiva, fn ($q) => $q->where('gestion_id', $gestionActiva->id))
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->paginate(15);

        return view('admin. academic.horarios.index', compact('horarios', 'gestionActiva'));
    }

    public function storeHorario(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'gestion_id' => ['required', 'exists:gestiones,id'],
            'docente_id' => ['required', 'exists:docentes,id'],
            'materia_id' => ['required', 'exists:materias,id'],
            'dia_semana' => ['required', 'integer', 'between:1,7'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'aula' => ['nullable', 'string', 'max:100'],
        ]);

        // Validar cruce de docente en el mismo día y horario
        $cruceDocente = Horario::query()
            ->where('gestion_id', $datos['gestion_id'])
            ->where('docente_id', $datos['docente_id'])
            ->where('dia_semana', $datos['dia_semana'])
            ->where('estado', 'activo')
            ->where(function ($q) use ($datos) {
                $q->whereBetween('hora_inicio', [$datos['hora_inicio'], $datos['hora_fin']])
                    ->orWhereBetween('hora_fin', [$datos['hora_inicio'], $datos['hora_fin']])
                    ->orWhere(fn ($sub) => $sub->where('hora_inicio', '<=', $datos['hora_inicio'])->where('hora_fin', '>=', $datos['hora_fin']));
            })
            ->exists();

        if ($cruceDocente) {
            throw ValidationException::withMessages([
                'docente_id' => 'El docente ya cuenta con otro horario asignado que se solapa en este bloque de tiempo.',
            ]);
        }

        $horario = Horario::create($datos + ['estado' => 'activo']);
        BitacoraService::registrar('crear_horario', 'horarios', $horario->id, null, $horario->toArray());

        return redirect()->route('admin.horarios.index')->with('success', 'Horario programado con éxito.');
    }

    // ==========================================
    // 4. INSCRIPCIONES
    // ==========================================
    public function indexInscripciones(Request $request): View
    {
        $gestionActiva = Gestion::activa();
        $inscripciones = Inscripcion::with(['estudiante', 'materia', 'gestionAcademica'])
            ->when($gestionActiva, fn ($q) => $q->where('gestion_id', $gestionActiva->id))
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin. academic.inscripciones.index', compact('inscripciones', 'gestionActiva'));
    }

    /**
     * @return array{
     *     gestion: string,
     *     materias: int,
     *     materias_revisadas: int,
     *     materias_pendientes: int,
     *     inscripciones_pendientes: int,
     *     decisiones_pendientes: int,
     *     semestres: array<int, array{nombre: string, total: int, revisadas: int, porcentaje: int}>,
     *     todos_resultados_revisados: bool,
     *     todos_revisados: bool
     * }
     */
    private function resumenCierre(string $gestion, ?int $gestionId = null): array
    {
        $materias = Materia::query()->get(['id', 'semestre']);
        $semestres = collect(['Primero', 'Segundo', 'Tercero', 'Cuarto'])->map(function (string $nombre) use ($materias, $gestion, $gestionId): array {
            $materiasSemestre = $materias->where('semestre', $nombre);
            $revisadas = $materiasSemestre->filter(fn (Materia $materia): bool => ! Inscripcion::query()
                ->where('materia_id', $materia->id)
                ->where(function (Builder $query) use ($gestion, $gestionId): void {
                    $this->filtrarGestion($query, 'inscripciones', $gestion, $gestionId);
                })
                ->where(function ($query): void {
                    $query->whereNotIn('estado', ['Aprobado', 'Reprobado'])
                        ->orWhereNull('resultado_final')
                        ->orWhereNotIn('resultado_final', ['Aprobado', 'Reprobado']);
                })
                ->exists())->count();

            $total = $materiasSemestre->count();

            return [
                'nombre' => $nombre,
                'total' => $total,
                'revisadas' => $revisadas,
                'porcentaje' => $total === 0 ? 100 : (int) round(($revisadas / $total) * 100),
            ];
        })->all();

        $pendientes = collect($semestres)->sum(fn (array $semestre): int => $semestre['total'] - $semestre['revisadas']);
        $inscripciones = Inscripcion::query()
            ->with(['estudiante' => fn ($query) => $query->withTrashed(), 'materia'])
            ->where(function (Builder $query) use ($gestion, $gestionId): void {
                $this->filtrarGestion($query, 'inscripciones', $gestion, $gestionId);
            })
            ->get();
        $inscripcionesPendientes = $inscripciones->filter(
            fn (Inscripcion $inscripcion): bool => ! in_array($inscripcion->estado, ['Aprobado', 'Reprobado'], true)
                || ! in_array($inscripcion->resultado_final, ['Aprobado', 'Reprobado'], true),
        )->count();
        $recomendaciones = $inscripcionesPendientes === 0
            ? $this->decisionesProgresion($inscripciones)
            : [];
        $decisionesGuardadas = $recomendaciones === []
            ? collect()
            : DB::table('decisiones_progresion')
                ->where('gestion_id', $gestionId)
                ->whereIn('estudiante_id', array_keys($recomendaciones))
                ->get()
                ->keyBy('estudiante_id');
        $decisionesPendientes = collect($recomendaciones)
            ->filter(function (array $recomendacion, int $estudianteId) use ($decisionesGuardadas): bool {
                $guardada = $decisionesGuardadas->get($estudianteId);

                return $guardada === null
                    || $guardada->decision_confirmada === null
                    || $guardada->decision_recomendada !== $recomendacion['decision'];
            })
            ->count();

        return [
            'gestion' => $gestion,
            'materias' => $materias->count(),
            'materias_revisadas' => $materias->count() - $pendientes,
            'materias_pendientes' => $pendientes,
            'inscripciones_pendientes' => $inscripcionesPendientes,
            'decisiones_pendientes' => $decisionesPendientes,
            'semestres' => $semestres,
            'todos_resultados_revisados' => $inscripcionesPendientes === 0,
            'todos_revisados' => $inscripcionesPendientes === 0 && $decisionesPendientes === 0,
        ];
    }

    /**
     * @param  array{inscripciones_pendientes: int, semestres: array<int, array{nombre: string, total: int, revisadas: int, porcentaje: int}>}  $cierre
     */
    private function mensajeCierrePendiente(array $cierre): string
    {
        $semestresPendientes = collect($cierre['semestres'])
            ->filter(fn (array $semestre): bool => $semestre['revisadas'] < $semestre['total'])
            ->pluck('nombre')
            ->implode(', ');
        $detalleSemestres = $semestresPendientes === ''
            ? ''
            : " Pendientes: {$semestresPendientes}.";

        return "La gestión no está concluida: faltan revisar {$cierre['inscripciones_pendientes']} inscripción(es), registrar sus resultados y confirmar {$cierre['decisiones_pendientes']} decisión(es) de progresión.{$detalleSemestres}";
    }

    /**
     * @param  Collection<int, Inscripcion>  $inscripciones
     * @return array<int, array{
     *     estudiante: ?Estudiante,
     *     decision: string,
     *     nuevo_semestre: ?string,
     *     nuevo_estado: ?string,
     *     materias_aprobadas: int,
     *     materias_reprobadas: int,
     *     puede_retirarse: bool,
     *     inscripciones: Collection<int, Inscripcion>,
     *     por_inscripcion: array<int, string>
     * }>
     */
    private function decisionesProgresion(Collection $inscripciones): array
    {
        $registrosPorEstudiante = $inscripciones->groupBy('estudiante_id');
        $materiasCuarto = Materia::query()
            ->whereRaw('LOWER(estado) = ?', ['activo'])
            ->get(['id', 'semestre'])
            ->filter(fn (Materia $materia): bool => Materia::nombreSemestre($materia->semestre) === 'Cuarto')
            ->pluck('id');
        $estudianteIdsCuarto = $registrosPorEstudiante
            ->filter(function (Collection $registros): bool {
                $estudiante = $registros->first()->estudiante;

                return $estudiante?->estado === 'activo'
                    && $this->numeroSemestre($estudiante->semestre) === 4;
            })
            ->keys();
        $materiasArchivadasAprobadas = $estudianteIdsCuarto->isNotEmpty() && $materiasCuarto->isNotEmpty()
            ? DB::table('inscripciones_archivadas')
                ->whereIn('estudiante_id', $estudianteIdsCuarto)
                ->whereIn('materia_id', $materiasCuarto)
                ->where('resultado_final', 'Aprobado')
                ->get(['estudiante_id', 'materia_id'])
                ->groupBy('estudiante_id')
            : collect();

        return $registrosPorEstudiante
            ->mapWithKeys(function (Collection $registros) use ($materiasCuarto, $materiasArchivadasAprobadas): array {
                $estudiante = $registros->first()->estudiante;
                $materiasReprobadas = $registros->filter(
                    fn (Inscripcion $inscripcion): bool => $inscripcion->resultado_final === 'Reprobado',
                )->count();
                $materiasAprobadas = $registros->filter(
                    fn (Inscripcion $inscripcion): bool => $inscripcion->resultado_final === 'Aprobado',
                )->count();
                $semestreActual = $this->numeroSemestre($estudiante?->semestre);
                $materiasCuartoAprobadas = $registros
                    ->filter(fn (Inscripcion $inscripcion): bool => $inscripcion->resultado_final === 'Aprobado'
                        && $materiasCuarto->contains($inscripcion->materia_id))
                    ->pluck('materia_id')
                    ->merge(
                        $materiasArchivadasAprobadas
                            ->get($registros->first()->estudiante_id, collect())
                            ->pluck('materia_id'),
                    )
                    ->unique();
                $aproboTodasLasMateriasDeCuarto = $materiasCuarto->isNotEmpty()
                    && $materiasCuarto->diff($materiasCuartoAprobadas)->isEmpty();
                $puedeRetirarse = $materiasReprobadas === 0 && $aproboTodasLasMateriasDeCuarto;
                $nuevoSemestre = null;
                $nuevoEstado = null;
                $decisionGeneral = 'sin_cambio';

                if ($estudiante?->estado === 'activo' && $semestreActual > 0) {
                    if ($materiasReprobadas === 0
                        && $semestreActual === 4
                        && $puedeRetirarse) {
                        $nuevoEstado = 'egresado';
                        $decisionGeneral = 'retiro';
                    } elseif ($materiasReprobadas === 0) {
                        if ($semestreActual < 4) {
                            $nuevoSemestre = $this->nombreSemestre($semestreActual + 1);
                            $decisionGeneral = 'avanza';
                        } else {
                            $decisionGeneral = 'pendiente_materias_cuarto';
                        }
                    } elseif ($materiasReprobadas <= 2 && $semestreActual < 4) {
                        $nuevoSemestre = $this->nombreSemestre($semestreActual + 1);
                        $decisionGeneral = 'avanza_con_arrastres';
                    } else {
                        $decisionGeneral = 'repite_semestre';
                    }
                }

                $porInscripcion = [];

                foreach ($registros as $inscripcion) {
                    $porInscripcion[$inscripcion->id] = $decisionGeneral === 'avanza_con_arrastres'
                        && $inscripcion->resultado_final === 'Reprobado'
                        ? 'arrastre'
                        : $decisionGeneral;
                }

                return [
                    $registros->first()->estudiante_id => [
                        'estudiante' => $estudiante,
                        'decision' => $decisionGeneral,
                        'nuevo_semestre' => $nuevoSemestre,
                        'nuevo_estado' => $nuevoEstado,
                        'materias_aprobadas' => $materiasAprobadas,
                        'materias_reprobadas' => $materiasReprobadas,
                        'puede_retirarse' => $puedeRetirarse,
                        'inscripciones' => $registros,
                        'por_inscripcion' => $porInscripcion,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @param  array{estudiante: ?Estudiante, decision: string, materias_reprobadas: int, puede_retirarse: bool}  $recomendacion
     * @return array<int, string>
     */
    private function opcionesDecisionProgresion(array $recomendacion): array
    {
        $semestre = $this->numeroSemestre($recomendacion['estudiante']?->semestre);

        if ($recomendacion['decision'] === 'sin_cambio' || $semestre === 0) {
            return ['sin_cambio'];
        }

        if ($semestre === 4) {
            $opciones = ['repite_semestre', 'pendiente_materias_cuarto'];

            if ($recomendacion['puede_retirarse']) {
                $opciones[] = 'retiro';
            }

            return $opciones;
        }

        $opciones = ['avanza', 'repite_semestre'];

        if ($recomendacion['materias_reprobadas'] > 0) {
            $opciones[] = 'avanza_con_arrastres';
        }

        return $opciones;
    }

    private function numeroSemestre(string|int|null $semestre): int
    {
        return match (strtolower((string) $semestre)) {
            'primero', '1' => 1,
            'segundo', '2' => 2,
            'tercero', '3' => 3,
            'cuarto', '4' => 4,
            default => 0,
        };
    }

    private function nombreSemestre(int $semestre): string
    {
        return [1 => 'Primero', 2 => 'Segundo', 3 => 'Tercero', 4 => 'Cuarto'][$semestre] ?? 'Primero';
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?Gestion}
     */
    private function periodoSeleccionado(Request $request): array
    {
        $gestionVigente = ConfiguracionAcademica::gestionVigente();
        $gestionActiva = Gestion::activa();
        $esDocente = $request->user()->isDocente() && ! $request->user()->isAdministrador();

        if ($esDocente) {
            $gestion = $gestionActiva !== null && $gestionVigente === $gestionActiva->codigo
                ? $gestionActiva->codigo
                : null;

            return [$gestion, $gestionVigente, $gestionActiva];
        }

        $gestion = ConfiguracionAcademica::normalizarCodigo(
            (string) $request->query('gestion', $gestionVigente ?? ''),
        );

        if (! preg_match('/^(I|II)-\d{4}$/', $gestion)) {
            $gestion = $gestionVigente ?? '';
        }

        $gestionSeleccionada = $gestion === ''
            ? null
            : Gestion::query()->where('codigo', $gestion)->first();

        return [$gestion !== '' ? $gestion : null, $gestionVigente, $gestionSeleccionada];
    }

    /**
     * @return Collection<int, string>
     */
    private function gestionesDisponibles(): Collection
    {
        return collect([ConfiguracionAcademica::gestionVigente()])
            ->merge(Gestion::query()->orderByDesc('codigo')->pluck('codigo'))
            ->merge(Inscripcion::query()->whereNotNull('gestion')->distinct()->pluck('gestion'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();
    }

    private function filtrarGestion(Builder|Relation $query, string $tabla, ?string $codigo, ?int $gestionId): void
    {
        if ($codigo === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $gestionQuery) use ($tabla, $codigo, $gestionId): void {
            if ($gestionId === null) {
                $gestionQuery->where("{$tabla}.gestion", $codigo);

                return;
            }

            $gestionQuery->where("{$tabla}.gestion_id", $gestionId)
                ->orWhere(function (Builder $legacyQuery) use ($tabla, $codigo): void {
                    $legacyQuery->whereNull("{$tabla}.gestion_id")
                        ->where("{$tabla}.gestion", $codigo);
                });
        });
    }

    /**
     * @return array{anio: int, periodo: string, fecha_inicio: string, fecha_fin: string, activa: bool}
     */
    private function atributosGestion(string $codigo): array
    {
        [$periodo, $anio] = explode('-', $codigo);
        $anio = (int) $anio;
        $esPrimerPeriodo = $periodo === 'I';

        return [
            'anio' => $anio,
            'periodo' => $esPrimerPeriodo ? '1' : '2',
            'fecha_inicio' => Carbon::create($anio, $esPrimerPeriodo ? 2 : 8, 1)->toDateString(),
            'fecha_fin' => Carbon::create($anio, $esPrimerPeriodo ? 7 : 11, $esPrimerPeriodo ? 31 : 30)->toDateString(),
            'activa' => false,
        ];
    }

    public function storeInscripcion(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'estudiante_id' => ['required', 'exists:estudiantes,id'],
            'materia_id' => ['required', 'exists:materias,id'],
            'gestion_id' => ['required', 'exists:gestiones,id'],
            'tipo' => ['required', Rule::in(['normal', 'arrastre'])],
        ]);

        // Verificar unicidad por estudiante, materia y gestión
        $existe = Inscripcion::query()
            ->where('estudiante_id', $datos['estudiante_id'])
            ->where('materia_id', $datos['materia_id'])
            ->where('gestion_id', $datos['gestion_id'])
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'materia_id' => 'El estudiante ya se encuentra inscrito en esta materia para la presente gestión.',
            ]);
        }

        $inscripcion = Inscripcion::create([
            ...$datos,
            'estado' => 'confirmada',
            'fecha_inscripcion' => now(),
        ]);

        BitacoraService::registrar('inscribir_estudiante', 'inscripciones', $inscripcion->id, null, $inscripcion->toArray());

        return redirect()->route('admin.inscripciones.index')->with('success', 'Inscripción procesada correctamente.');
    }
}
