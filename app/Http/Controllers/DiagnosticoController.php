<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Diagnostico;
use App\Models\Equipo;
use App\Models\Heartbeat;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiagnosticoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:30'],
            'nivel' => ['nullable', Rule::in(['info', 'advertencia', 'critica'])],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);
        $buscar = trim($filtros['buscar'] ?? '');

        $esDocente = $request->user()->isDocente() && ! $request->user()->isAdministrador();
        $equiposQuery = Equipo::query();
        $diagnosticosQuery = Diagnostico::query();
        if ($esDocente) {
            $equiposAsignados = Asignacion::forTeacher($request->user())
                ->select('equipo_id')
                ->distinct();
            $equiposQuery->whereIn('id', $equiposAsignados);
            $diagnosticosQuery->whereIn(
                'equipo_id',
                (clone $equiposAsignados)
            );
        }

        $ahora = now();
        $equiposRevision = (clone $equiposQuery)
            ->with(['activo', 'ultimoHeartbeat.diagnosticos'])
            ->orderBy('identificador')
            ->paginate(12, ['*'], 'equipos_page')
            ->withQueryString();
        $equiposRevision->getCollection()->each(function (Equipo $equipo) use ($ahora): void {
            $heartbeat = $equipo->ultimoHeartbeat;
            $tieneMetricas = $heartbeat !== null && collect([
                $heartbeat->cpu_uso,
                $heartbeat->ram_uso,
                $heartbeat->almacenamiento_uso,
                $heartbeat->bateria,
                $heartbeat->temperatura,
            ])->contains(fn ($valor): bool => $valor !== null);
            $estaConectado = $heartbeat?->fecha_hora !== null
                && $heartbeat->fecha_hora->greaterThanOrEqualTo($ahora->copy()->subMinutes(Heartbeat::ONLINE_TIMEOUT_MINUTES))
                && $heartbeat->fecha_hora->lessThanOrEqualTo($ahora);
            $estadoRevision = match (true) {
                $heartbeat === null => 'sin_datos',
                ! $estaConectado => 'desconectado',
                $heartbeat->diagnosticos->isNotEmpty() => 'con_alertas',
                ! $tieneMetricas => 'sin_metricas',
                default => 'sin_alertas',
            };

            $equipo->setAttribute('estado_revision', $estadoRevision);
        });

        $estadisticas = [
            'total' => (clone $diagnosticosQuery)->count(),
            'criticas' => (clone $diagnosticosQuery)->where('nivel', 'critica')->count(),
            'advertencias' => (clone $diagnosticosQuery)->where('nivel', 'advertencia')->count(),
            'informativos' => (clone $diagnosticosQuery)->where('nivel', 'info')->count(),
        ];

        $diagnosticos = (clone $diagnosticosQuery)
            ->with(['equipo.activo', 'heartbeat'])
            ->when($buscar !== '', function (Builder $query) use ($buscar): void {
                $query->where(function (Builder $diagnosticoQuery) use ($buscar): void {
                    $diagnosticoQuery->where('descripcion', 'like', "%{$buscar}%")
                        ->orWhere('tipo', 'like', "%{$buscar}%")
                        ->orWhere('nivel', 'like', "%{$buscar}%")
                        ->orWhere('codigo_regla', 'like', "%{$buscar}%")
                        ->orWhereHas('equipo', function (Builder $equipoQuery) use ($buscar): void {
                            $equipoQuery->where('identificador', 'like', "%{$buscar}%")
                                ->orWhereHas('activo', function (Builder $activoQuery) use ($buscar): void {
                                    $activoQuery->where('codigo', 'like', "%{$buscar}%")
                                        ->orWhere('marca', 'like', "%{$buscar}%")
                                        ->orWhere('modelo', 'like', "%{$buscar}%");
                                });
                        });
                });
            })
            ->when($filtros['tipo'] ?? null, fn (Builder $query, string $tipo) => $query->whereRaw('LOWER(tipo) = LOWER(?)', [$tipo]))
            ->when($filtros['nivel'] ?? null, fn (Builder $query, string $nivel) => $query->whereRaw('LOWER(nivel) = LOWER(?)', [$nivel]))
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $desde) => $query->whereDate('fecha_hora', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $hasta) => $query->whereDate('fecha_hora', '<=', $hasta))
            ->latest('fecha_hora')
            ->paginate(12)
            ->withQueryString();

        $niveles = (clone $diagnosticosQuery)
            ->select('nivel')
            ->distinct()
            ->orderBy('nivel')
            ->pluck('nivel');
        $tipos = (clone $diagnosticosQuery)
            ->select('tipo')
            ->distinct()
            ->orderBy('tipo')
            ->pluck('tipo');

        return view('diagnosticos.index', compact(
            'diagnosticos',
            'equiposRevision',
            'estadisticas',
            'filtros',
            'niveles',
            'tipos',
        ));
    }
}
