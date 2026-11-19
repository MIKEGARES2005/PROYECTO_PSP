<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionAcademica;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Services\NotificacionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PanelSeguimientoDocenteController extends Controller
{
    private const EVENTOS = [
        'observacion' => 'Observación',
        'congelamiento' => 'Solicitud de congelamiento',
        'reincorporacion' => 'Solicitud de reincorporación',
        'retiro' => 'Solicitud de retiro',
        'abandono' => 'Posible abandono',
    ];

    public function index(Request $request): View
    {
        $docenteId = $request->user()->docente_id;
        abort_unless($docenteId !== null, 403);

        $gestionActiva = Gestion::activa();
        $codigoGestion = ConfiguracionAcademica::gestionVigente();
        $semestres = $this->semestresDocente($docenteId, $gestionActiva, $codigoGestion);
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'semestre' => ['nullable', 'string', Rule::in($semestres)],
        ]);
        $buscar = trim($filtros['buscar'] ?? '');
        $semestre = $filtros['semestre'] ?? '';

        $estudiantes = Estudiante::query()
            ->whereHas('inscripciones', function (Builder $query) use ($docenteId, $gestionActiva, $codigoGestion, $semestre): void {
                $this->restringirInscripcionesDocente($query, $docenteId, $gestionActiva, $codigoGestion);
                $this->restringirInscripcionesPorSemestre($query, $semestre);
            })
            ->with(['inscripciones' => function (HasMany $query) use ($docenteId, $gestionActiva, $codigoGestion, $semestre): void {
                $this->restringirInscripcionesDocente($query, $docenteId, $gestionActiva, $codigoGestion);
                $this->restringirInscripcionesPorSemestre($query, $semestre);
                $query->with('materia:id,nombre,sigla');
            }])
            ->when($buscar !== '', function (Builder $query) use ($buscar): void {
                $query->where(function (Builder $estudiantesQuery) use ($buscar): void {
                    $estudiantesQuery->where('nombres', 'like', "%{$buscar}%")
                        ->orWhere('apellido_paterno', 'like', "%{$buscar}%")
                        ->orWhere('apellido_materno', 'like', "%{$buscar}%")
                        ->orWhere('ci', 'like', "%{$buscar}%")
                        ->orWhere('matricula', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('semestre')
            ->orderBy('apellido_paterno')
            ->orderBy('nombres')
            ->paginate(12)
            ->withQueryString();

        return view('panel-docente.seguimiento', [
            'estudiantes' => $estudiantes,
            'buscar' => $buscar,
            'semestres' => $semestres,
            'semestreSeleccionado' => $semestre,
            'gestionVigente' => $codigoGestion,
            'eventos' => self::EVENTOS,
        ]);
    }

    public function store(Request $request, Estudiante $estudiante): RedirectResponse
    {
        $datos = $request->validate([
            'materia_id' => ['required', 'integer'],
            'tipo_evento' => ['required', Rule::in(array_keys(self::EVENTOS))],
            'descripcion' => ['required', 'string', 'max:1000'],
            'fecha_evento' => ['required', 'date'],
        ]);
        $docenteId = $request->user()->docente_id;
        abort_unless($docenteId !== null, 403);

        $gestionActiva = Gestion::activa();
        $codigoGestion = ConfiguracionAcademica::gestionVigente();
        $inscripcion = Inscripcion::query()
            ->whereBelongsTo($estudiante)
            ->where('materia_id', $datos['materia_id'])
            ->where(function (Builder $query) use ($docenteId, $gestionActiva, $codigoGestion): void {
                $this->restringirInscripcionesDocente($query, $docenteId, $gestionActiva, $codigoGestion);
            })
            ->with('materia:id,nombre')
            ->firstOrFail();

        $docente = $request->user()->docente;
        $mensaje = implode("\n", [
            'Docente: '.$docente->nombreCompleto().'.',
            'Estudiante: '.$estudiante->nombreCompleto().' (Matrícula: '.($estudiante->matricula ?: 'Sin matrícula').').',
            'Materia: '.$inscripcion->materia->nombre.'.',
            'Gestión: '.$codigoGestion.'.',
            'Evento: '.self::EVENTOS[$datos['tipo_evento']].'.',
            'Fecha del evento: '.$datos['fecha_evento'].'.',
            'Descripción: '.$datos['descripcion'],
            'Pendiente de revisión administrativa; este reporte no modifica el estado ni el historial del estudiante.',
        ]);

        NotificacionService::notificarAdmins(
            'seguimiento_docente',
            'Reporte docente de seguimiento',
            $mensaje,
            route('seguimiento-estudiantil.show', $estudiante),
        );

        return redirect()
            ->route('panel-docente.seguimiento')
            ->with('success', 'El reporte fue enviado a administración para su revisión.');
    }

    /**
     * @return array<int, string>
     */
    private function semestresDocente(int $docenteId, ?Gestion $gestionActiva, ?string $codigoGestion): array
    {
        if ($gestionActiva === null || $codigoGestion !== $gestionActiva->codigo) {
            return [];
        }

        return Materia::query()
            ->whereRaw('LOWER(estado) = ?', ['activo'])
            ->whereHas('horarios', function (Builder $query) use ($docenteId, $gestionActiva, $codigoGestion): void {
                $this->restringirHorariosDocente($query, $docenteId, $gestionActiva, $codigoGestion);
            })
            ->pluck('semestre')
            ->map(fn (string $semestre): ?string => Materia::nombreSemestre($semestre))
            ->filter()
            ->unique()
            ->sortBy(fn (string $semestre): int => (int) array_search($semestre, Materia::SEMESTRES, true))
            ->values()
            ->all();
    }

    private function restringirInscripcionesDocente(
        Builder|Relation $query,
        int $docenteId,
        ?Gestion $gestionActiva,
        ?string $codigoGestion,
    ): void {
        $query->where('estado', 'Cursando');

        if ($gestionActiva === null || $codigoGestion !== $gestionActiva->codigo) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $periodoQuery) use ($gestionActiva, $codigoGestion): void {
            $periodoQuery->where('gestion_id', $gestionActiva->id)
                ->orWhere(function (Builder $legacyQuery) use ($codigoGestion): void {
                    $legacyQuery
                        ->whereNull('gestion_id')
                        ->where('gestion', $codigoGestion);
                });
        })->whereHas('materia', function (Builder $materiaQuery) use ($docenteId, $gestionActiva, $codigoGestion): void {
            $materiaQuery
                ->whereRaw('LOWER(estado) = ?', ['activo'])
                ->whereHas('horarios', function (Builder $horarioQuery) use ($docenteId, $gestionActiva, $codigoGestion): void {
                    $this->restringirHorariosDocente($horarioQuery, $docenteId, $gestionActiva, $codigoGestion);
                });
        });
    }

    private function restringirHorariosDocente(
        Builder|Relation $query,
        int $docenteId,
        Gestion $gestionActiva,
        string $codigoGestion,
    ): void {
        $query
            ->where('docente_id', $docenteId)
            ->whereRaw('LOWER(estado) = ?', ['activo'])
            ->where(function (Builder $periodoQuery) use ($gestionActiva, $codigoGestion): void {
                $periodoQuery->where('gestion_id', $gestionActiva->id)
                    ->orWhere(function (Builder $legacyQuery) use ($codigoGestion): void {
                        $legacyQuery
                            ->whereNull('gestion_id')
                            ->where('gestion', $codigoGestion);
                    });
            });
    }

    private function restringirInscripcionesPorSemestre(Builder|Relation $query, string $semestre): void
    {
        if ($semestre === '') {
            return;
        }

        $numeroSemestre = array_search($semestre, Materia::SEMESTRES, true);

        $query->whereHas('materia', function (Builder $materiaQuery) use ($semestre, $numeroSemestre): void {
            $materiaQuery->whereIn('semestre', [$semestre, (string) $numeroSemestre]);
        });
    }
}
