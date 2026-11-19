<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\ConfiguracionAcademica;
use App\Models\Equipo;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Heartbeat;
use App\Models\Materia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AsignacionController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in([
                'pendiente_entrega',
                'activa',
                'devolucion_pendiente',
                'devuelta',
                'vencida',
                'con_incidencia',
                'cancelada',
            ])],
        ]);
        $busqueda = trim($filtros['buscar'] ?? '');
        $asignacionesQuery = $this->visibleAssignments();
        $estadosAbiertos = ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia'];
        $ahora = now();
        $desde = $ahora->copy()->subMinutes(Heartbeat::ONLINE_TIMEOUT_MINUTES);
        $estadisticas = [
            'abiertas' => (clone $asignacionesQuery)->whereIn('estado', $estadosAbiertos)->count(),
            'pendientes_entrega' => (clone $asignacionesQuery)->where('estado', 'pendiente_entrega')->count(),
            'vencidas' => (clone $asignacionesQuery)
                ->whereIn('estado', ['vencida', 'activa', 'pendiente_entrega', 'devolucion_pendiente', 'con_incidencia'])
                ->where('fecha_devolucion_prevista', '<', $ahora)
                ->count(),
            'dispositivos_conectados' => (clone $asignacionesQuery)
                ->whereIn('estado', $estadosAbiertos)
                ->whereNotNull('equipo_id')
                ->whereHas('equipo.heartbeats', fn (Builder $query) => $query->whereBetween('fecha_hora', [$desde, $ahora]))
                ->distinct('equipo_id')
                ->count('equipo_id'),
        ];
        $asignaciones = (clone $asignacionesQuery)
            ->with([
                'equipo.activo',
                'equipo.ultimoHeartbeat',
                'complemento.activo',
                'estudiante',
                'invitado',
                'horario.materia',
            ])
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $query->where(function (Builder $assignmentQuery) use ($busqueda): void {
                    $assignmentQuery
                        ->whereHas('equipo.activo', function (Builder $assetQuery) use ($busqueda): void {
                            $assetQuery->where('codigo', 'like', "%{$busqueda}%")
                                ->orWhere('nombre', 'like', "%{$busqueda}%")
                                ->orWhere('marca', 'like', "%{$busqueda}%")
                                ->orWhere('modelo', 'like', "%{$busqueda}%");
                        })
                        ->orWhereHas('complemento.activo', function (Builder $assetQuery) use ($busqueda): void {
                            $assetQuery->where('codigo', 'like', "%{$busqueda}%")
                                ->orWhere('nombre', 'like', "%{$busqueda}%")
                                ->orWhere('marca', 'like', "%{$busqueda}%")
                                ->orWhere('modelo', 'like', "%{$busqueda}%");
                        })
                        ->orWhereHas('estudiante', function (Builder $studentQuery) use ($busqueda): void {
                            $studentQuery->where('nombres', 'like', "%{$busqueda}%")
                                ->orWhere('apellido_paterno', 'like', "%{$busqueda}%")
                                ->orWhere('apellido_materno', 'like', "%{$busqueda}%")
                                ->orWhere('matricula', 'like', "%{$busqueda}%");
                        })
                        ->orWhereHas('invitado', function (Builder $guestQuery) use ($busqueda): void {
                            $guestQuery->where('nombres', 'like', "%{$busqueda}%")
                                ->orWhere('apellido_paterno', 'like', "%{$busqueda}%")
                                ->orWhere('apellido_materno', 'like', "%{$busqueda}%")
                                ->orWhere('ci', 'like', "%{$busqueda}%");
                        })
                        ->orWhereHas('horario.materia', function (Builder $subjectQuery) use ($busqueda): void {
                            $subjectQuery->where('nombre', 'like', "%{$busqueda}%")
                                ->orWhere('sigla', 'like', "%{$busqueda}%");
                        });
                });
            })
            ->when(isset($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->latest()
            ->paginate(12)
            ->withQueryString();
        $equiposDisponibles = $this->equiposDisponibles()->with(['activo', 'ultimoHeartbeat'])->get();

        return view('asignaciones.index', compact('asignaciones', 'equiposDisponibles', 'estadisticas'));
    }

    public function create(): View
    {
        $gestion = $this->gestionVigente();
        $equipos = $this->equiposDisponibles()->with(['activo', 'ultimoHeartbeat'])->get();
        $horarios = $this->availableSchedules($gestion)->orderBy('horarios.dia_semana')->orderBy('horarios.hora_inicio')->get();
        $materiaIds = $horarios->pluck('materia_id')->unique()->values();
        $materias = Materia::query()
            ->where('estado', 'activo')
            ->whereIn('id', $materiaIds)
            ->orderBy('semestre')
            ->orderBy('nombre')
            ->get();
        $estudiantes = Estudiante::query()
            ->where('estado', 'activo')
            ->where(function (Builder $query): void {
                $this->filtrarPorSemestreNormalizado($query, 'estudiantes.semestre');
            })
            ->whereHas('inscripciones', function (Builder $query) use ($gestion, $materiaIds): void {
                $this->aplicarGestion($query, 'inscripciones', $gestion);
                $query->whereRaw('LOWER(estado) = ?', ['cursando'])
                    ->whereIn('materia_id', $materiaIds);
            })
            ->with(['inscripciones' => function ($query) use ($gestion, $materiaIds): void {
                $this->aplicarGestion($query, 'inscripciones', $gestion);
                $query->whereRaw('LOWER(estado) = ?', ['cursando'])
                    ->whereIn('materia_id', $materiaIds)
                    ->select(['id', 'estudiante_id', 'materia_id']);
            }])
            ->orderBy('nombres')
            ->get();
        $semestres = collect(Materia::SEMESTRES)->values();

        return view('asignaciones.create', compact('equipos', 'estudiantes', 'horarios', 'materias', 'semestres'));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'equipo_id' => ['required', 'exists:equipos,id'],
            'estudiante_id' => ['required', 'exists:estudiantes,id'],
            'materia_id' => ['required', 'exists:materias,id'],
            'horario_id' => ['required', 'exists:horarios,id'],
            'semestre' => ['required', Rule::in(array_values(Materia::SEMESTRES))],
            'modalidad' => ['required', Rule::in(['individual', 'grupal'])],
            'codigo_grupo' => ['nullable', 'required_if:modalidad,grupal', 'string', 'max:30'],
            'fecha_entrega' => ['required', 'date'],
            'fecha_devolucion_prevista' => ['required', 'date', 'after_or_equal:fecha_entrega'],
            'observaciones' => ['nullable', 'string'],
        ]);
        $gestion = $this->gestionVigente();
        $horario = $this->availableSchedules($gestion)->where('horarios.id', $datos['horario_id'])->first();
        if ($horario === null) {
            return back()->withInput()->withErrors(['horario_id' => 'El horario no pertenece a una materia autorizada.']);
        }
        if (Materia::nombreSemestre($horario->semestre) !== Materia::nombreSemestre($datos['semestre'])) {
            return back()->withInput()->withErrors(['horario_id' => 'La materia y el horario no pertenecen al semestre seleccionado.']);
        }
        if ((int) $horario->materia_id !== (int) $datos['materia_id']) {
            return back()->withInput()->withErrors(['horario_id' => 'El horario no pertenece a la materia seleccionada.']);
        }
        $estudiante = Estudiante::query()->whereKey($datos['estudiante_id'])->where('estado', 'activo')->first();
        if ($estudiante === null || Materia::nombreSemestre($estudiante->semestre) !== Materia::nombreSemestre($datos['semestre'])) {
            return back()->withInput()->withErrors(['estudiante_id' => 'El estudiante no pertenece al semestre seleccionado.']);
        }
        $inscripcionActiva = DB::table('inscripciones')
            ->where('estudiante_id', $datos['estudiante_id'])
            ->where('materia_id', $horario->materia_id)
            ->whereRaw('LOWER(estado) = ?', ['cursando'])
            ->where(function (QueryBuilder $query) use ($gestion): void {
                $this->aplicarGestion($query, 'inscripciones', $gestion);
            })
            ->exists();
        if (! $inscripcionActiva) {
            return back()->withInput()->withErrors(['estudiante_id' => 'El estudiante no tiene una inscripción activa en esta materia.']);
        }
        $equipo = Equipo::with('activo')->findOrFail($datos['equipo_id']);
        if ($equipo->modo_uso !== $datos['modalidad']) {
            return back()->withInput()->withErrors(['modalidad' => 'La modalidad debe coincidir con la modalidad del equipo.']);
        }
        if (! $this->equipoPuedeAsignarse($equipo, $datos['modalidad'])) {
            return back()->withInput()->withErrors(['equipo_id' => 'El equipo seleccionado no está disponible para una asignación.']);
        }
        $datos['codigo'] = $this->codigoUnico('ASG');
        $datos['tipo'] = 'asignado';
        $datos['codigo_grupo'] = $datos['modalidad'] === 'grupal' ? $datos['codigo_grupo'] : null;
        unset($datos['semestre'], $datos['materia_id']);
        $datos['autorizado_por'] = auth()->id();
        $datos['entregado_por'] = auth()->id();
        $datos['entrega_confirmada_at'] = now();
        $datos['estado'] = 'activa';
        DB::transaction(function () use ($datos, $equipo): void {
            Asignacion::create($datos);
            $equipo->activo()->update(['estado_uso' => 'asignado']);
        });

        return redirect()->route('asignaciones.index')->with('success', 'Asignación creada correctamente.');
    }

    public function finalizar(int $id): RedirectResponse
    {
        $asignacion = $this->visibleAssignments()->with('equipo')->findOrFail($id);
        if (! $asignacion->esta_abierta) {
            return back()->withErrors(['asignacion' => 'El registro seleccionado ya está cerrado.']);
        }
        DB::transaction(function () use ($asignacion): void {
            $asignacion->update(['estado' => 'devuelta', 'fecha_devolucion_real' => now(), 'devolucion_recibida_por' => auth()->id(), 'devolucion_aprobada_por' => auth()->id()]);
            $this->liberarEquipoSiCorresponde($asignacion->equipo);
        });

        return redirect()->route('asignaciones.index')->with('success', 'Devolución registrada correctamente.');
    }

    public function cambiarEquipo(Request $request, int $id): RedirectResponse
    {
        $asignacion = $this->visibleAssignments()->with('equipo.activo')->findOrFail($id);
        $datos = $request->validate(['equipo_id' => ['required', 'exists:equipos,id']]);
        $nuevoEquipo = Equipo::with('activo')->findOrFail($datos['equipo_id']);
        if (! $asignacion->esta_abierta) {
            return back()->withErrors(['equipo_id' => 'Solo puedes cambiar el equipo de un registro abierto.']);
        }
        if ($nuevoEquipo->modo_uso !== $asignacion->modalidad) {
            return back()->withErrors(['equipo_id' => 'El nuevo equipo debe conservar la misma modalidad.']);
        }
        if (! $this->equipoPuedeAsignarse($nuevoEquipo, $asignacion->modalidad, $asignacion->id)) {
            return back()->withErrors(['equipo_id' => 'El nuevo equipo no está disponible.']);
        }
        DB::transaction(function () use ($asignacion, $nuevoEquipo): void {
            $equipoAnterior = $asignacion->equipo;
            $asignacion->update(['equipo_id' => $nuevoEquipo->id]);
            $nuevoEquipo->activo()->update(['estado_uso' => 'asignado']);
            $this->liberarEquipoSiCorresponde($equipoAnterior);
        });

        return back()->with('success', 'El equipo fue cambiado correctamente.');
    }

    private function availableSchedules(?Gestion $gestion = null): QueryBuilder
    {
        $query = DB::table('horarios')
            ->join('materias', 'materias.id', '=', 'horarios.materia_id')
            ->whereRaw('LOWER(horarios.estado) = ?', ['activo'])
            ->whereRaw('LOWER(materias.estado) = ?', ['activo'])
            ->select(['horarios.id', 'horarios.docente_id', 'horarios.materia_id', 'horarios.dia_semana', 'horarios.hora_inicio', 'horarios.hora_fin', 'horarios.aula', 'materias.nombre as materia_nombre', 'materias.semestre']);
        $this->aplicarGestion($query, 'horarios', $gestion);
        if (auth()->user()->tipo_usuario === 'docente') {
            $query->where('horarios.docente_id', auth()->user()->docente_id);
        }

        return $query;
    }

    private function gestionVigente(): ?Gestion
    {
        $codigo = ConfiguracionAcademica::gestionVigente();

        return Gestion::query()->where('codigo', $codigo)->first() ?? Gestion::vigente();
    }

    private function valoresSemestre(): array
    {
        return array_values(array_unique(array_merge(array_keys(Materia::SEMESTRES), array_values(Materia::SEMESTRES))));
    }

    private function filtrarPorSemestreNormalizado(Builder|QueryBuilder $query, string $column): void
    {
        $query->where(function (Builder|QueryBuilder $semestreQuery) use ($column): void {
            foreach ($this->valoresSemestre() as $semestre) {
                $semestreQuery->orWhereRaw('LOWER(TRIM('.$column.')) = ?', [mb_strtolower((string) $semestre)]);
            }
        });
    }

    private function aplicarGestion(Builder|QueryBuilder|Relation $query, string $table, ?Gestion $gestion): void
    {
        $codigo = ConfiguracionAcademica::gestionVigente();

        $query->where(function (Builder|QueryBuilder $gestionQuery) use ($table, $gestion, $codigo): void {
            if ($gestion !== null) {
                $gestionQuery->where("{$table}.gestion_id", $gestion->id)
                    ->orWhere("{$table}.gestion", $codigo);

                return;
            }

            $gestionQuery->where("{$table}.gestion", $codigo);
        });
    }

    private function visibleAssignments(): Builder
    {
        $query = Asignacion::query();
        if (auth()->user()->tipo_usuario === 'docente') {
            $query->whereIn('horario_id', DB::table('horarios')->where('docente_id', auth()->user()->docente_id)->select('id'));
        }

        return $query;
    }

    private function equiposDisponibles(): Builder
    {
        return Equipo::query()->whereHas('activo', fn (Builder $query) => $query->whereIn('estado_uso', ['disponible', 'asignado']))->where(function (Builder $query): void {
            $query->where('modo_uso', 'grupal')->orWhere(function (Builder $individualQuery): void {
                $individualQuery->where('modo_uso', 'individual')->whereDoesntHave('asignaciones', fn (Builder $assignmentQuery) => $assignmentQuery->whereIn('estado', ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia']));
            });
        })->orderBy('identificador');
    }

    private function equipoPuedeAsignarse(Equipo $equipo, string $modalidad, ?int $exceptoAsignacion = null): bool
    {
        if (! in_array($equipo->activo?->estado_uso, ['disponible', 'asignado'], true)) {
            return false;
        }
        if ($modalidad === 'grupal') {
            return true;
        }

        return ! $equipo->asignaciones()->when($exceptoAsignacion !== null, fn (Builder $query) => $query->whereKeyNot($exceptoAsignacion))->whereIn('estado', ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia'])->exists();
    }

    private function liberarEquipoSiCorresponde(?Equipo $equipo): void
    {
        if ($equipo === null) {
            return;
        }
        $tieneActivas = $equipo->asignaciones()->whereIn('estado', ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia'])->exists();
        if (! $tieneActivas) {
            $equipo->activo()->update(['estado_uso' => 'disponible']);
        }
    }

    private function codigoUnico(string $prefijo): string
    {
        do {
            $codigo = $prefijo.'-'.Str::upper(Str::random(8));
        } while (Asignacion::where('codigo', $codigo)->exists());

        return $codigo;
    }
}
