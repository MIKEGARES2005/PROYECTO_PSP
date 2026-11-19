<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionAcademica;
use App\Models\Gestion;
use App\Models\Materia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PanelEstudiantilController extends Controller
{
    public function index(Request $request): View
    {
        $docenteId = $request->user()->docente_id;
        $gestionActiva = Gestion::activa();
        $codigoGestion = ConfiguracionAcademica::gestionVigente();
        $gestion = $gestionActiva !== null && $codigoGestion === $gestionActiva->codigo
            ? $gestionActiva->codigo
            : null;
        $materias = Materia::query()
            ->whereRaw('LOWER(estado) = ?', ['activo'])
            ->whereHas('horarios', function (Builder $query) use ($docenteId, $gestionActiva, $codigoGestion): void {
                $query->where('docente_id', $docenteId)
                    ->whereRaw('LOWER(estado) = ?', ['activo'])
                    ->where(function (Builder $scheduleQuery) use ($gestionActiva, $codigoGestion): void {
                        if ($gestionActiva === null || $codigoGestion !== $gestionActiva->codigo) {
                            $scheduleQuery->whereRaw('1 = 0');

                            return;
                        }

                        $scheduleQuery->where('gestion_id', $gestionActiva->id)
                            ->orWhere(function (Builder $legacyQuery) use ($gestionActiva): void {
                                $legacyQuery->whereNull('gestion_id')
                                    ->where('gestion', $gestionActiva->codigo);
                            });
                    });
            })
            ->orderBy('semestre')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla', 'semestre']);
        $materiasPorSemestre = $materias->groupBy('semestre');
        $semestres = collect(Materia::SEMESTRES)
            ->map(fn (string $nombre, int $numero): array => [
                'numero' => $numero,
                'nombre' => $nombre,
                'materias' => $materiasPorSemestre->get($nombre, collect()),
            ])
            ->filter(fn (array $semestre): bool => $semestre['materias']->isNotEmpty())
            ->values();

        return view('panel-docente.estudiantes', compact('gestion', 'semestres'));
    }
}
