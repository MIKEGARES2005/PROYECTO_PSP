<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Equipo;
use App\Models\Heartbeat;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MonitoreoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['conectados', 'desconectados', 'sin_datos'])],
        ]);

        $ahora = now();
        $desde = $ahora->copy()->subMinutes(Heartbeat::ONLINE_TIMEOUT_MINUTES);
        $equiposQuery = Equipo::query();
        $estadosAsignacionAbierta = [
            'pendiente_entrega',
            'activa',
            'devolucion_pendiente',
            'vencida',
            'con_incidencia',
        ];

        $equiposConectados = (clone $equiposQuery)
            ->whereHas('heartbeats', fn (Builder $query) => $query->whereBetween('fecha_hora', [$desde, $ahora]));
        $equiposSinDatos = (clone $equiposQuery)->doesntHave('heartbeats');
        $totalEquipos = (clone $equiposQuery)->count();
        $totalConectados = (clone $equiposConectados)->count();
        $totalSinDatos = (clone $equiposSinDatos)->count();
        $estadisticas = [
            'total' => $totalEquipos,
            'conectados' => $totalConectados,
            'desconectados' => $totalEquipos - $totalConectados - $totalSinDatos,
            'sin_datos' => $totalSinDatos,
            'alertas_abiertas' => Alerta::query()
                ->whereIn('equipo_id', (clone $equiposQuery)->select('id'))
                ->whereIn('estado', ['abierta', 'atendida'])
                ->count(),
        ];

        $equipos = (clone $equiposQuery)
            ->with([
                'activo',
                'ultimoHeartbeat',
                'asignaciones' => function (Relation $query) use ($estadosAsignacionAbierta): void {
                    $query->whereIn('estado', $estadosAsignacionAbierta)
                        ->with(['estudiante', 'invitado', 'horario.materia', 'horario.docente'])
                        ->latest('fecha_entrega');
                },
            ])
            ->withCount([
                'alertas as alertas_abiertas_count' => fn (Builder $query) => $query->whereIn('estado', ['abierta', 'atendida']),
            ])
            ->when(($filtros['buscar'] ?? '') !== '', function (Builder $query) use ($filtros): void {
                $buscar = trim($filtros['buscar']);
                $query->where(function (Builder $equipoQuery) use ($buscar): void {
                    $equipoQuery->where('identificador', 'like', "%{$buscar}%")
                        ->orWhereHas('activo', function (Builder $activoQuery) use ($buscar): void {
                            $activoQuery->where('codigo', 'like', "%{$buscar}%")
                                ->orWhere('marca', 'like', "%{$buscar}%")
                                ->orWhere('modelo', 'like', "%{$buscar}%")
                                ->orWhere('numero_serie', 'like', "%{$buscar}%");
                        });
                });
            })
            ->when(($filtros['estado'] ?? '') !== '', function (Builder $query) use ($filtros, $desde, $ahora): void {
                if ($filtros['estado'] === 'conectados') {
                    $query->whereHas('heartbeats', fn (Builder $heartbeatQuery) => $heartbeatQuery->whereBetween('fecha_hora', [$desde, $ahora]));

                    return;
                }

                if ($filtros['estado'] === 'sin_datos') {
                    $query->doesntHave('heartbeats');

                    return;
                }

                $query->whereHas('heartbeats')
                    ->whereDoesntHave('heartbeats', fn (Builder $heartbeatQuery) => $heartbeatQuery->whereBetween('fecha_hora', [$desde, $ahora]));
            })
            ->orderBy('identificador')
            ->paginate(12)
            ->withQueryString();

        $equipos->getCollection()->each(function (Equipo $equipo) use ($desde, $ahora): void {
            $ultimaFecha = $equipo->ultimoHeartbeat?->fecha_hora;
            $estadoConexion = match (true) {
                $ultimaFecha === null => 'sin_datos',
                $ultimaFecha->greaterThanOrEqualTo($desde) && $ultimaFecha->lessThanOrEqualTo($ahora) => 'conectado',
                default => 'desconectado',
            };

            $equipo->setAttribute('estado_conexion', $estadoConexion);
        });

        $ultimaActualizacion = Heartbeat::query()
            ->whereIn('equipo_id', (clone $equiposQuery)->select('id'))
            ->max('fecha_hora');
        $ultimaActualizacion = $ultimaActualizacion === null ? null : Carbon::parse($ultimaActualizacion);

        return view('monitoreo.index', compact(
            'equipos',
            'estadisticas',
            'filtros',
            'ultimaActualizacion',
        ));
    }

    public function storePairingCode(Equipo $equipo): RedirectResponse
    {
        $codigo = Str::upper(Str::random(12));
        $equipo->loadMissing('activo');

        $equipo->forceFill([
            'token_hash' => null,
            'token_creado_en' => null,
            'token_expira_en' => null,
            'codigo_vinculacion_hash' => hash('sha256', $codigo),
            'codigo_vinculacion_expira_en' => now()->addMinutes(10),
        ])->save();

        return back()
            ->with('success', 'Código de vinculación generado. Solo se mostrará una vez y vence en 10 minutos.')
            ->with('pairing_code', [
                'equipo_id' => $equipo->id,
                'equipo_identificador' => $equipo->identificador,
                'codigo_patrimonial' => $equipo->activo?->codigo,
                'codigo' => $codigo,
            ]);
    }

    public function destroyPairing(Equipo $equipo): RedirectResponse
    {
        $equipo->forceFill([
            'token_hash' => null,
            'token_creado_en' => null,
            'token_expira_en' => null,
            'codigo_vinculacion_hash' => null,
            'codigo_vinculacion_expira_en' => null,
        ])->save();

        return back()->with('success', 'Dispositivo desvinculado. Su credencial anterior ya no permite conectarse.');
    }
}
