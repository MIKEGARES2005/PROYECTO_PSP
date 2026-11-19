<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\ConfiguracionAcademica;
use App\Models\Equipo;
use App\Models\Gestion;
use App\Models\Heartbeat;
use App\Models\Incidencia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

class EstudianteDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user('student')->load('estudiante');
        $notificaciones = $user->notificaciones()
            ->latest('created_at')
            ->take(8)
            ->get();
        $notificacionesNoLeidas = $user->notificaciones()->whereNull('leida_en')->count();
        $gestion = Gestion::activa();
        $gestionVigente = ConfiguracionAcademica::gestionVigente();

        if ($gestion === null || $gestionVigente !== $gestion->codigo) {
            $gestionVigente = null;
        }

        $asignacionesDelEstudiante = Asignacion::query();
        if ($user->estudiante !== null) {
            $asignacionesDelEstudiante->where('estudiante_id', $user->estudiante->id);
        } else {
            $asignacionesDelEstudiante->whereRaw('1 = 0');
        }

        $asignacionesActivas = (clone $asignacionesDelEstudiante)
            ->whereIn('estado', ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia'])
            ->whereNotNull('equipo_id');
        $equiposDelEstudiante = (clone $asignacionesActivas)
            ->select('equipo_id')
            ->distinct();
        $ahora = now();
        $limiteHeartbeat = $ahora->copy()->subMinutes(Heartbeat::ONLINE_TIMEOUT_MINUTES);
        $equiposConectados = Equipo::query()
            ->whereIn('id', $equiposDelEstudiante)
            ->whereHas('heartbeats', fn (Builder $query) => $query->whereBetween('fecha_hora', [$limiteHeartbeat, $ahora]))
            ->count();
        $incidenciasPendientes = Incidencia::query()
            ->whereIn('asignacion_id', (clone $asignacionesActivas)->select('id'))
            ->whereIn('estado', ['abierta', 'en_revision'])
            ->count();
        $equiposAsignados = (clone $asignacionesActivas)
            ->with([
                'equipo.activo',
                'equipo.ultimoHeartbeat.diagnosticos',
                'horario.materia',
                'incidencias' => fn (Relation $query) => $query
                    ->whereIn('estado', ['abierta', 'en_revision'])
                    ->latest('fecha_reporte'),
            ])
            ->withCount([
                'incidencias as incidencias_pendientes_count' => fn (Builder $query) => $query->whereIn('estado', ['abierta', 'en_revision']),
            ])
            ->latest()
            ->take(5)
            ->get();

        return view('estudiante.dashboard', [
            'user' => $user,
            'estudiante' => $user->estudiante,
            'notificaciones' => $notificaciones,
            'notificacionesNoLeidas' => $notificacionesNoLeidas,
            'gestionVigente' => $gestionVigente,
            'asignacionesActivas' => (clone $asignacionesActivas)->count(),
            'equiposConectados' => $equiposConectados,
            'incidenciasPendientes' => $incidenciasPendientes,
            'equiposAsignados' => $equiposAsignados,
        ]);
    }
}
