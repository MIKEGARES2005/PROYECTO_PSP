<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Equipo;
use App\Models\Incidencia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IncidenciaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['pendientes', 'resueltas'])],
            'prioridad' => ['nullable', Rule::in(['baja', 'media', 'alta', 'critica', 'Baja', 'Media', 'Alta', 'Critica'])],
            'origen' => ['nullable', Rule::in(['manual', 'automatica', 'estudiante', 'app'])],
        ]);
        $busqueda = trim($filtros['buscar'] ?? '');
        $filtros['prioridad'] = strtolower($filtros['prioridad'] ?? '');
        $incidenciasQuery = $this->visibleIncidents();
        $estadisticas = [
            'pendientes' => (clone $incidenciasQuery)->whereIn('estado', ['abierta', 'en_revision'])->count(),
            'prioritarias' => (clone $incidenciasQuery)
                ->whereIn('estado', ['abierta', 'en_revision'])
                ->whereIn('prioridad', ['alta', 'critica'])
                ->count(),
            'automaticas' => (clone $incidenciasQuery)->where('origen', 'automatica')->count(),
            'app' => (clone $incidenciasQuery)->where('origen', 'app')->count(),
            'resueltas' => (clone $incidenciasQuery)->where('estado', 'resuelta')->count(),
        ];

        $incidencias = (clone $incidenciasQuery)->with([
            'activo.equipo.ultimoHeartbeat',
            'asignacion.estudiante',
            'asignacion.invitado',
            'alerta',
            'reportadoPor',
            'resueltoPor',
        ])
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $query->where(function (Builder $incidentQuery) use ($busqueda): void {
                    $incidentQuery->where('tipo', 'like', "%{$busqueda}%")
                        ->orWhere('descripcion', 'like', "%{$busqueda}%")
                        ->orWhere('estado', 'like', "%{$busqueda}%")
                        ->orWhere('origen', 'like', "%{$busqueda}%")
                        ->orWhereHas('activo', function (Builder $assetQuery) use ($busqueda): void {
                            $assetQuery->where('codigo', 'like', "%{$busqueda}%")
                                ->orWhere('marca', 'like', "%{$busqueda}%")
                                ->orWhere('modelo', 'like', "%{$busqueda}%")
                                ->orWhereHas('equipo', fn (Builder $equipmentQuery) => $equipmentQuery->where('identificador', 'like', "%{$busqueda}%"));
                        })
                        ->orWhereHas('asignacion.estudiante', function (Builder $studentQuery) use ($busqueda): void {
                            $studentQuery->where('nombres', 'like', "%{$busqueda}%")
                                ->orWhere('apellido_paterno', 'like', "%{$busqueda}%")
                                ->orWhere('apellido_materno', 'like', "%{$busqueda}%")
                                ->orWhere('matricula', 'like', "%{$busqueda}%");
                        });
                });
            })
            ->when(isset($filtros['estado']) && $filtros['estado'] !== '', function (Builder $query) use ($filtros): void {
                if ($filtros['estado'] === 'resueltas') {
                    $query->whereIn('estado', ['resuelta', 'descartada']);

                    return;
                }

                $query->whereIn('estado', ['abierta', 'en_revision']);
            })
            ->when(isset($filtros['prioridad']) && $filtros['prioridad'] !== '', function (Builder $query) use ($filtros): void {
                $query->where('prioridad', $filtros['prioridad']);
            })
            ->when(isset($filtros['origen']) && $filtros['origen'] !== '', fn (Builder $query) => $query->where('origen', $filtros['origen']))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('incidencias.index', compact('incidencias', 'estadisticas', 'filtros'));
    }

    public function create(): View
    {
        $esDocente = auth()->user()->isDocente();
        $asignaciones = ($esDocente
            ? $this->teacherAssignments(true)
            : Asignacion::query()->where('estado', 'activa')->whereNotNull('equipo_id'))
            ->with([
                'equipo.activo',
                'estudiante',
                'invitado',
            ])
            ->latest()
            ->get();

        $equipos = $esDocente
            ? Equipo::query()->with('activo')->whereIn(
                'id',
                $this->teacherAssignments(true)->select('equipo_id')
            )->orderBy('identificador')->get()
            : Equipo::query()->with('activo')->orderBy('identificador')->get();

        return view('incidencias.create', compact(
            'equipos',
            'asignaciones',
            'esDocente'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $esDocente = $request->user()->isDocente();
        $datos = $request->validate([
            'equipo_id' => [$esDocente ? 'nullable' : 'required', 'exists:equipos,id'],
            'asignacion_id' => [$esDocente ? 'required' : 'nullable', 'exists:asignaciones,id'],
            'tipo' => ['required', Rule::in(['Hardware', 'Software', 'Red', 'Bateria', 'Pantalla', 'Periferico', 'Otro'])],
            'descripcion' => ['required', 'string', 'max:5000'],
            'prioridad' => ['required', Rule::in(['baja', 'media', 'alta', 'critica', 'Baja', 'Media', 'Alta', 'Critica'])],
            'estado' => [$esDocente ? 'nullable' : 'required', Rule::in(['abierta', 'en_revision', 'Reportada', 'En revision'])],
            'fecha_reporte' => [$esDocente ? 'nullable' : 'required', 'date'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $asignacion = null;
        $equipo = null;
        if (! empty($datos['asignacion_id'])) {
            $consultaAsignacion = $esDocente
                ? $this->teacherAssignments(true)
                : Asignacion::query();
            $asignacion = $consultaAsignacion->find($datos['asignacion_id']);

            if ($asignacion === null) {
                return back()
                    ->withInput()
                    ->withErrors(['asignacion_id' => 'La asignación no está dentro de tu ámbito autorizado.']);
            }

            if (isset($datos['equipo_id']) && (int) $datos['equipo_id'] !== (int) $asignacion->equipo_id) {
                return back()
                    ->withInput()
                    ->withErrors(['equipo_id' => 'El equipo no coincide con la asignación seleccionada.']);
            }

            $equipo = Equipo::query()->find($asignacion->equipo_id);
        } elseif (isset($datos['equipo_id'])) {
            $equipo = Equipo::query()->find($datos['equipo_id']);
        }

        if ($esDocente) {
            $datos['estado'] = 'abierta';
            $datos['fecha_reporte'] = now();
        } elseif (isset($datos['estado'])) {
            $datos['estado'] = match (strtolower(str_replace(' ', '_', $datos['estado']))) {
                'reportada' => 'abierta',
                'en_revision' => 'en_revision',
                default => $datos['estado'],
            };
        }

        if ($equipo === null || $equipo->activo_id === null) {
            return back()
                ->withInput()
                ->withErrors(['equipo_id' => 'El equipo seleccionado no tiene un activo patrimonial asociado.']);
        }

        $datos['activo_id'] = $equipo->activo_id;
        $datos['prioridad'] = strtolower($datos['prioridad']);
        $datos['origen'] = 'manual';
        $datos['reportado_por'] = $request->user()->id;
        unset($datos['equipo_id']);

        Incidencia::create($datos);

        return redirect()
            ->route('incidencias.index')
            ->with('success', 'Incidencia registrada correctamente.');
    }

    public function resolver(Request $request, int $id): RedirectResponse
    {
        $incidencia = Incidencia::findOrFail($id);

        if (strtolower($incidencia->estado) === 'resuelta') {
            return back()->withErrors([
                'incidencia' => 'La incidencia ya está resuelta.',
            ]);
        }

        $incidencia->update([
            'estado' => 'resuelta',
            'fecha_resolucion' => now(),
            'resuelto_por' => $request->user()->id,
        ]);

        return redirect()
            ->route('incidencias.index')
            ->with('success', 'Incidencia marcada como resuelta.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $incidencia = Incidencia::findOrFail($id);
        $incidencia->delete();

        return redirect()
            ->route('incidencias.index')
            ->with('success', 'Incidencia eliminada correctamente.');
    }

    private function visibleIncidents(): Builder
    {
        $query = Incidencia::query();

        if (auth()->user()->isDocente()) {
            $equipoIds = $this->teacherAssignments()->select('equipo_id')->distinct();
            $query->where(function (Builder $visibleQuery) use ($equipoIds): void {
                $visibleQuery->whereIn('asignacion_id', $this->teacherAssignments()->select('id'))
                    ->orWhereHas('activo.equipo', fn (Builder $equipoQuery) => $equipoQuery->whereIn('id', $equipoIds));
            });
        }

        return $query;
    }

    private function teacherAssignments(bool $activeOnly = false): Builder
    {
        $horarios = DB::table('horarios')
            ->where('docente_id', auth()->user()->docente_id);

        if ($activeOnly) {
            $horarios->where('estado', 'activo');
        }

        $query = Asignacion::query()
            ->whereIn(
                'horario_id',
                $horarios->select('id')
            );

        if ($activeOnly) {
            $query->where('estado', 'activa')
                ->whereNotNull('equipo_id');
        }

        return $query;
    }
}
