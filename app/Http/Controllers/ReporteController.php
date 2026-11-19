<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Asignacion;
use App\Models\Complemento;
use App\Models\ConfiguracionAcademica;
use App\Models\Diagnostico;
use App\Models\Equipo;
use App\Models\Gestion;
use App\Models\Heartbeat;
use App\Models\Incidencia;
use App\Models\Inscripcion;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReporteController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'gestion' => ['nullable', 'string', 'regex:/^(I|II)-\d{4}$/'],
            'tipo_periodo' => ['nullable', 'in:semanal,mensual,anual'],
            'fecha_periodo' => ['nullable', 'date'],
        ]);
        $esAdministrador = $request->user()->isAdministrador();
        $tipoPeriodo = $filtros['tipo_periodo'] ?? 'semanal';
        $fechaPeriodo = $filtros['fecha_periodo'] ?? now()->toDateString();

        $consultas = $this->consultasDeReporte(
            $request->user(),
            $filtros['desde'] ?? null,
            $filtros['hasta'] ?? null,
        );
        $equipos = $consultas['equipos'];
        $asignaciones = $consultas['asignaciones'];
        $incidencias = $consultas['incidencias'];
        $estadisticas = $this->estadisticas(
            $equipos,
            $asignaciones,
            $incidencias,
            $consultas['complementos'],
        );

        $asignacionesRecientes = (clone $asignaciones)->with([
            'equipo.activo',
            'complemento.activo',
            'estudiante',
            'invitado',
            'horario.materia',
        ])
            ->latest('created_at')
            ->take(5)
            ->get();
        $incidenciasRecientes = (clone $incidencias)->with([
            'activo',
            'asignacion.estudiante',
            'asignacion.invitado',
        ])
            ->latest('fecha_reporte')
            ->take(5)
            ->get();
        $reporteAcademico = null;
        $gestionesAcademicas = collect();
        $gestionAcademica = null;

        if ($esAdministrador) {
            $gestionesAcademicas = Gestion::query()
                ->orderByDesc('codigo')
                ->pluck('codigo')
                ->merge(DB::table('inscripciones_archivadas')->distinct()->pluck('gestion'))
                ->filter()
                ->unique()
                ->sortDesc()
                ->values();
            $gestionVigente = ConfiguracionAcademica::gestionVigente();
            $gestionAcademica = $filtros['gestion']
                ?? ($gestionVigente && $gestionesAcademicas->contains($gestionVigente)
                    ? $gestionVigente
                    : $gestionesAcademicas->first());

            if ($gestionAcademica !== null && ! $gestionesAcademicas->contains($gestionAcademica)) {
                throw ValidationException::withMessages([
                    'gestion' => 'Selecciona una gestión académica disponible en el sistema.',
                ]);
            }

            $gestionModelo = $gestionAcademica === null
                ? null
                : Gestion::query()->where('codigo', $gestionAcademica)->first();
            $resumenProgresion = $this->resumenProgresion($gestionModelo?->id, $gestionAcademica);
            $inscripciones = Inscripcion::query();

            if ($gestionAcademica !== null) {
                $inscripciones->where(function (Builder $query) use ($gestionAcademica, $gestionModelo): void {
                    if ($gestionModelo !== null) {
                        $query->where('gestion_id', $gestionModelo->id)
                            ->orWhere(function (Builder $legacyQuery) use ($gestionAcademica): void {
                                $legacyQuery->whereNull('gestion_id')
                                    ->where('gestion', $gestionAcademica);
                            });

                        return;
                    }

                    $query->where('gestion', $gestionAcademica);
                });
            }

            $inscripcionesActivas = $gestionAcademica === null
                ? 0
                : (clone $inscripciones)->count();

            if ($gestionAcademica !== null && $inscripcionesActivas > 0) {
                $totalesAcademicos = (clone $inscripciones)
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN estado = 'Cursando' THEN 1 ELSE 0 END) as cursando")
                    ->selectRaw("SUM(CASE WHEN estado = 'Cursando' AND resultado_reportado IS NOT NULL THEN 1 ELSE 0 END) as en_revision")
                    ->selectRaw("SUM(CASE WHEN estado = 'Aprobado' THEN 1 ELSE 0 END) as aprobados")
                    ->selectRaw("SUM(CASE WHEN estado = 'Reprobado' THEN 1 ELSE 0 END) as reprobados")
                    ->selectRaw("SUM(CASE WHEN estado = 'Cursando' AND (parcial_1 IS NULL OR parcial_2 IS NULL) THEN 1 ELSE 0 END) as calificaciones_pendientes")
                    ->first();

                $materiasAcademicas = (clone $inscripciones)
                    ->join('materias', 'materias.id', '=', 'inscripciones.materia_id')
                    ->select([
                        'materias.semestre',
                        'materias.nombre as materia',
                        'materias.sigla',
                    ])
                    ->selectRaw('COUNT(inscripciones.id) as total')
                    ->selectRaw("SUM(CASE WHEN inscripciones.estado = 'Aprobado' THEN 1 ELSE 0 END) as aprobados")
                    ->selectRaw("SUM(CASE WHEN inscripciones.estado = 'Reprobado' THEN 1 ELSE 0 END) as reprobados")
                    ->selectRaw("SUM(CASE WHEN inscripciones.estado = 'Cursando' AND inscripciones.resultado_reportado IS NOT NULL THEN 1 ELSE 0 END) as en_revision")
                    ->selectRaw("SUM(CASE WHEN inscripciones.estado = 'Cursando' AND (inscripciones.parcial_1 IS NULL OR inscripciones.parcial_2 IS NULL) THEN 1 ELSE 0 END) as calificaciones_pendientes")
                    ->selectRaw('AVG(inscripciones.nota_final) as promedio')
                    ->groupBy('materias.semestre', 'materias.nombre', 'materias.sigla')
                    ->orderBy('materias.semestre')
                    ->orderBy('materias.nombre')
                    ->get();

                $reporteAcademico = [
                    'archivado' => false,
                    'total' => (int) $totalesAcademicos->total,
                    'cursando' => (int) $totalesAcademicos->cursando,
                    'en_revision' => (int) $totalesAcademicos->en_revision,
                    'aprobados' => (int) $totalesAcademicos->aprobados,
                    'reprobados' => (int) $totalesAcademicos->reprobados,
                    'calificaciones_pendientes' => (int) $totalesAcademicos->calificaciones_pendientes,
                    'progresan' => $resumenProgresion['progresan'],
                    'progresion' => $resumenProgresion,
                    'tiene_movimientos' => true,
                    'materias' => $materiasAcademicas,
                ];
            } elseif ($gestionAcademica !== null) {
                $totalArchivadas = DB::table('inscripciones_archivadas')
                    ->where('gestion', $gestionAcademica)
                    ->count();
                $totalesAcademicos = DB::table('inscripciones_archivadas')
                    ->where('gestion', $gestionAcademica)
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN resultado_final = 'Aprobado' THEN 1 ELSE 0 END) as aprobados")
                    ->selectRaw("SUM(CASE WHEN resultado_final = 'Reprobado' THEN 1 ELSE 0 END) as reprobados")
                    ->selectRaw('AVG(nota_final) as promedio')
                    ->first();

                $materiasAcademicas = DB::table('inscripciones_archivadas')
                    ->where('gestion', $gestionAcademica)
                    ->select([
                        'semestre',
                        'materia_nombre as materia',
                    ])
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN resultado_final = 'Aprobado' THEN 1 ELSE 0 END) as aprobados")
                    ->selectRaw("SUM(CASE WHEN resultado_final = 'Reprobado' THEN 1 ELSE 0 END) as reprobados")
                    ->selectRaw("SUM(CASE WHEN decision_progresion IN ('avanza', 'avanza_con_arrastres') THEN 1 ELSE 0 END) as progresan")
                    ->selectRaw('AVG(nota_final) as promedio')
                    ->groupBy('semestre', 'materia_id', 'materia_nombre')
                    ->orderBy('semestre')
                    ->orderBy('materia_nombre')
                    ->get();

                $reporteAcademico = [
                    'archivado' => ! (bool) $gestionModelo?->activa,
                    'total' => (int) ($totalesAcademicos->total ?? 0),
                    'cursando' => 0,
                    'en_revision' => 0,
                    'aprobados' => (int) ($totalesAcademicos->aprobados ?? 0),
                    'reprobados' => (int) ($totalesAcademicos->reprobados ?? 0),
                    'calificaciones_pendientes' => 0,
                    'progresan' => $resumenProgresion['progresan'],
                    'progresion' => $resumenProgresion,
                    'tiene_movimientos' => $totalArchivadas > 0 || $resumenProgresion['tiene_movimientos'],
                    'promedio' => $totalesAcademicos->promedio,
                    'materias' => $materiasAcademicas,
                ];
            }
        }

        return view('reportes.index', compact(
            'estadisticas',
            'asignacionesRecientes',
            'incidenciasRecientes',
            'filtros',
            'esAdministrador',
            'reporteAcademico',
            'gestionesAcademicas',
            'gestionAcademica',
            'tipoPeriodo',
            'fechaPeriodo',
        ));
    }

    public function downloadPdf(Request $request): Response
    {
        $filtros = $request->validate([
            'tipo_periodo' => ['required', 'in:semanal,mensual,anual'],
            'fecha_periodo' => ['required', 'date'],
        ]);
        $usuario = $request->user();
        $tipoPeriodo = $filtros['tipo_periodo'];
        $fechaReferencia = Carbon::parse($filtros['fecha_periodo']);
        [$desde, $hasta] = match ($tipoPeriodo) {
            'semanal' => [
                $fechaReferencia->copy()->startOfWeek(Carbon::MONDAY),
                $fechaReferencia->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            'mensual' => [
                $fechaReferencia->copy()->startOfMonth(),
                $fechaReferencia->copy()->endOfMonth(),
            ],
            'anual' => [
                $fechaReferencia->copy()->startOfYear(),
                $fechaReferencia->copy()->endOfYear(),
            ],
        };
        $desdeFecha = $desde->toDateString();
        $hastaFecha = $hasta->toDateString();

        $consultas = $this->consultasDeReporte($usuario, $desdeFecha, $hastaFecha);
        $estadisticas = $this->estadisticas(
            $consultas['equipos'],
            $consultas['asignaciones'],
            $consultas['incidencias'],
            $consultas['complementos'],
        );
        $nombreTipoPeriodo = match ($tipoPeriodo) {
            'semanal' => 'Semanal',
            'mensual' => 'Mensual',
            'anual' => 'Anual',
        };
        $nombreArchivo = sprintf(
            'reporte-%s-%s-a-%s.pdf',
            $tipoPeriodo,
            $desdeFecha,
            $hastaFecha,
        );

        return Pdf::loadView('reportes.pdf', [
            'estadisticas' => $estadisticas,
            'tipoPeriodo' => $nombreTipoPeriodo,
            'desde' => $desde,
            'hasta' => $hasta,
            'generadoPor' => $usuario->username,
            'generadoEn' => now(),
        ])->setPaper('a4', 'portrait')->download($nombreArchivo);
    }

    /**
     * @return array{
     *     equipos: Builder,
     *     asignaciones: Builder,
     *     incidencias: Builder,
     *     complementos: Builder
     * }
     */
    private function consultasDeReporte(User $usuario, ?string $desde, ?string $hasta): array
    {
        $equipos = Equipo::query();
        $asignaciones = Asignacion::query()
            ->when($desde, fn (Builder $query, string $desde) => $query->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn (Builder $query, string $hasta) => $query->whereDate('created_at', '<=', $hasta));
        $incidencias = Incidencia::query()
            ->when($desde, fn (Builder $query, string $desde) => $query->whereDate('fecha_reporte', '>=', $desde))
            ->when($hasta, fn (Builder $query, string $hasta) => $query->whereDate('fecha_reporte', '<=', $hasta));
        $asignacionesDocente = Asignacion::query();
        $complementos = Complemento::query();

        if ($usuario->isDocente() && ! $usuario->isAdministrador()) {
            $asignacionesDocente->forTeacher($usuario);
            $equipos->whereIn(
                'id',
                (clone $asignacionesDocente)->select('equipo_id')->distinct()
            );
            $asignaciones->forTeacher($usuario);
            $incidencias->whereIn(
                'asignacion_id',
                (clone $asignacionesDocente)->select('id')
            );
            $complementos->whereHas('equipos', fn (Builder $query) => $query->whereIn(
                'equipos.id',
                (clone $equipos)->select('id')
            ));
        }

        return compact('equipos', 'asignaciones', 'incidencias', 'complementos');
    }

    /**
     * @return array{
     *     totalEquipos: int,
     *     equiposDisponibles: int,
     *     equiposEnUso: int,
     *     equiposOtros: int,
     *     totalAsignaciones: int,
     *     asignacionesActivas: int,
     *     asignacionesFinalizadas: int,
     *     totalIncidencias: int,
     *     incidenciasPendientes: int,
     *     incidenciasApp: int,
     *     incidenciasResueltas: int,
     *     totalAccesorios: int,
     *     dispositivosConectados: int,
     *     dispositivosDesconectados: int,
     *     dispositivosSinReportes: int,
     *     totalDiagnosticos: int,
     *     diagnosticosCriticos: int,
     *     alertasAbiertas: int
     * }
     */
    private function estadisticas(
        Builder $equipos,
        Builder $asignaciones,
        Builder $incidencias,
        Builder $complementos,
    ): array {
        $totalEquipos = (clone $equipos)->count();
        $equiposDisponibles = (clone $equipos)
            ->whereHas('activo', fn (Builder $query) => $query->where('estado_uso', 'disponible'))
            ->count();
        $equiposEnUso = (clone $equipos)
            ->whereHas('activo', fn (Builder $query) => $query->whereIn('estado_uso', ['asignado', 'prestado']))
            ->count();
        $equiposOtros = (clone $equipos)
            ->whereHas('activo', fn (Builder $query) => $query->whereNotIn('estado_uso', ['disponible', 'asignado', 'prestado']))
            ->count();
        $totalAsignaciones = (clone $asignaciones)->count();
        $asignacionesActivas = (clone $asignaciones)->whereIn('estado', [
            'pendiente_entrega',
            'activa',
            'devolucion_pendiente',
            'vencida',
            'con_incidencia',
        ])->count();
        $asignacionesFinalizadas = (clone $asignaciones)->whereIn('estado', ['devuelta', 'cancelada'])->count();
        $totalIncidencias = (clone $incidencias)->count();
        $incidenciasResueltas = (clone $incidencias)->where('estado', 'resuelta')->count();
        $ahora = now();
        $conexionDesde = $ahora->copy()->subMinutes(Heartbeat::ONLINE_TIMEOUT_MINUTES);
        $equiposConReporte = (clone $equipos)->whereHas('heartbeats');
        $diagnosticosQuery = Diagnostico::query()->whereIn('equipo_id', (clone $equipos)->select('equipos.id'));
        $alertasQuery = Alerta::query()->whereIn('equipo_id', (clone $equipos)->select('equipos.id'));

        return [
            'totalEquipos' => $totalEquipos,
            'equiposDisponibles' => $equiposDisponibles,
            'equiposEnUso' => $equiposEnUso,
            'equiposOtros' => $equiposOtros,
            'totalAsignaciones' => $totalAsignaciones,
            'asignacionesActivas' => $asignacionesActivas,
            'asignacionesFinalizadas' => $asignacionesFinalizadas,
            'totalIncidencias' => $totalIncidencias,
            'incidenciasPendientes' => $totalIncidencias - $incidenciasResueltas,
            'incidenciasApp' => (clone $incidencias)->where('origen', 'app')->count(),
            'incidenciasResueltas' => $incidenciasResueltas,
            'totalAccesorios' => $complementos->count(),
            'dispositivosConectados' => (clone $equipos)
                ->whereHas('heartbeats', fn (Builder $query) => $query->whereBetween('fecha_hora', [$conexionDesde, $ahora]))
                ->count(),
            'dispositivosDesconectados' => (clone $equiposConReporte)
                ->whereDoesntHave('heartbeats', fn (Builder $query) => $query->whereBetween('fecha_hora', [$conexionDesde, $ahora]))
                ->count(),
            'dispositivosSinReportes' => (clone $equipos)->whereDoesntHave('heartbeats')->count(),
            'totalDiagnosticos' => (clone $diagnosticosQuery)->count(),
            'diagnosticosCriticos' => (clone $diagnosticosQuery)->where('nivel', 'critica')->count(),
            'alertasAbiertas' => (clone $alertasQuery)->whereIn('estado', ['abierta', 'atendida'])->count(),
        ];
    }

    /**
     * @return array{
     *     avanzan: int,
     *     avanzan_con_arrastres: int,
     *     repiten: int,
     *     egresados: int,
     *     congelamientos: int,
     *     progresan: int,
     *     tiene_movimientos: bool
     * }
     */
    private function resumenProgresion(?int $gestionId, ?string $codigoGestion): array
    {
        if ($gestionId === null || $codigoGestion === null) {
            return [
                'avanzan' => 0,
                'avanzan_con_arrastres' => 0,
                'repiten' => 0,
                'egresados' => 0,
                'congelamientos' => 0,
                'progresan' => 0,
                'tiene_movimientos' => false,
            ];
        }

        $decisiones = DB::table('decisiones_progresion')
            ->where('gestion_id', $gestionId)
            ->whereNotNull('decision_confirmada')
            ->selectRaw("SUM(CASE WHEN decision_confirmada = 'avanza' THEN 1 ELSE 0 END) as avanzan")
            ->selectRaw("SUM(CASE WHEN decision_confirmada = 'avanza_con_arrastres' THEN 1 ELSE 0 END) as avanzan_con_arrastres")
            ->selectRaw("SUM(CASE WHEN decision_confirmada = 'repite_semestre' THEN 1 ELSE 0 END) as repiten")
            ->selectRaw("SUM(CASE WHEN decision_confirmada = 'retiro' THEN 1 ELSE 0 END) as retiros")
            ->first();
        $eventos = DB::table('historial_seguimiento_estudiantes')
            ->where('gestion_id', $gestionId)
            ->selectRaw("SUM(CASE WHEN tipo_evento IN ('egreso', 'retiro') THEN 1 ELSE 0 END) as egresados")
            ->selectRaw("SUM(CASE WHEN tipo_evento = 'congelamiento' THEN 1 ELSE 0 END) as congelamientos")
            ->first();

        $avanzan = (int) ($decisiones->avanzan ?? 0);
        $avanzanConArrastres = (int) ($decisiones->avanzan_con_arrastres ?? 0);
        $repiten = (int) ($decisiones->repiten ?? 0);
        $progresan = $avanzan + $avanzanConArrastres;

        if ($progresan === 0) {
            $progresan = DB::table('inscripciones_archivadas')
                ->where('gestion', $codigoGestion)
                ->whereIn('decision_progresion', ['avanza', 'avanza_con_arrastres', 'arrastre'])
                ->distinct()
                ->count('estudiante_id');
        }

        $egresados = max((int) ($eventos->egresados ?? 0), (int) ($decisiones->retiros ?? 0));
        $congelamientos = (int) ($eventos->congelamientos ?? 0);

        return [
            'avanzan' => $avanzan,
            'avanzan_con_arrastres' => $avanzanConArrastres,
            'repiten' => $repiten,
            'egresados' => $egresados,
            'congelamientos' => $congelamientos,
            'progresan' => $progresan,
            'tiene_movimientos' => $avanzan > 0
                || $avanzanConArrastres > 0
                || $repiten > 0
                || $egresados > 0
                || $congelamientos > 0,
        ];
    }
}
