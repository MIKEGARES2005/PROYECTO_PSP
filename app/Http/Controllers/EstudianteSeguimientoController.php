<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionAcademica;
use App\Models\Gestion;
use App\Models\Inscripcion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstudianteSeguimientoController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user('student')->load('estudiante')->estudiante;
        $gestionActiva = Gestion::activa();
        $codigoGestion = ConfiguracionAcademica::gestionVigente();
        $gestion = $gestionActiva !== null && $codigoGestion === $gestionActiva->codigo
            ? $gestionActiva->codigo
            : null;
        $inscripciones = Inscripcion::query()->with(['materia.horarios' => function ($query) use ($gestion, $gestionActiva): void {
            $query->where('estado', 'activo')->where(function ($scheduleQuery) use ($gestion, $gestionActiva): void {
                if ($gestion === null || $gestionActiva === null) {
                    $scheduleQuery->whereRaw('1 = 0');

                    return;
                }

                $scheduleQuery->where('gestion_id', $gestionActiva->id)
                    ->orWhere(function ($legacyQuery) use ($gestion): void {
                        $legacyQuery->whereNull('gestion_id')->where('gestion', $gestion);
                    });
            })->with('docente');
        }])->where('estudiante_id', $student->id)->where(function ($query) use ($gestion, $gestionActiva): void {
            if ($gestion === null || $gestionActiva === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('gestion_id', $gestionActiva->id)
                ->orWhere(function ($legacyQuery) use ($gestion): void {
                    $legacyQuery->whereNull('gestion_id')->where('gestion', $gestion);
                });
        })->get();

        $decisionQuery = DB::table('decisiones_progresion as decisiones')
            ->join('gestiones', 'gestiones.id', '=', 'decisiones.gestion_id')
            ->where('decisiones.estudiante_id', $student->id)
            ->whereNotNull('decisiones.decision_confirmada');
        $decisionProgresion = null;

        if ($gestion !== null && $gestionActiva !== null) {
            $decisionProgresion = (clone $decisionQuery)
                ->where('decisiones.gestion_id', $gestionActiva->id)
                ->first([
                    'decisiones.decision_confirmada',
                    'decisiones.motivo',
                    'decisiones.revisado_en',
                    'gestiones.codigo as gestion',
                ]);
        }

        $decisionProgresion ??= $decisionQuery
            ->orderByDesc('decisiones.revisado_en')
            ->orderByDesc('decisiones.id')
            ->first([
                'decisiones.decision_confirmada',
                'decisiones.motivo',
                'decisiones.revisado_en',
                'gestiones.codigo as gestion',
            ]);

        return view('estudiante.seguimiento', compact(
            'student',
            'inscripciones',
            'gestion',
            'decisionProgresion',
        ));
    }
}
