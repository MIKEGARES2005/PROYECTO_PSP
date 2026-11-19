<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Equipo;
use App\Models\Notificacion;
use App\Models\Solicitud;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudEquipoController extends Controller
{
    public function index(): View
    {
        $requests = $this->visibleRequests()->with(['estudianteSolicitante', 'horario.materia', 'horario.docente'])->latest()->get();
        $returns = $this->visibleAssignments()->with(['estudiante', 'equipo.activo', 'horario.materia'])->where('estado', 'devolucion_pendiente')->latest()->get();
        $equipment = Equipo::with('activo')->whereHas('activo', fn (Builder $q) => $q->where('estado_uso', 'disponible'))
            ->whereDoesntHave('asignaciones', fn (Builder $q) => $q->whereIn('estado', ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia']))->orderBy('identificador')->get();

        return view('solicitudes.equipos.index', compact('requests', 'returns', 'equipment'));
    }

    public function approve(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorizeRequest($solicitud);
        $data = $request->validate(['equipo_id' => ['required', 'exists:equipos,id'], 'fecha_devolucion_prevista' => ['required', 'date', 'after_or_equal:today'], 'estado_fisico_entrega' => ['required', Rule::in(['bueno', 'regular', 'danado'])]]);
        if ($solicitud->estado !== 'pendiente') {
            return back()->withErrors(['solicitud' => 'La solicitud ya fue procesada.']);
        }
        $equipment = Equipo::with('activo')->findOrFail($data['equipo_id']);
        if ($equipment->tipo_equipo !== $solicitud->tipo_equipo || $equipment->activo?->estado_uso !== 'disponible' || $equipment->asignaciones()->whereIn('estado', ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia'])->exists()) {
            return back()->withErrors(['equipo_id' => 'El equipo no coincide o ya no está disponible.']);
        }
        $assignment = null;
        DB::transaction(function () use ($data, $solicitud, $equipment, &$assignment): void {
            $assignment = Asignacion::create(['codigo' => $this->uniqueCode(), 'tipo' => 'asignado', 'modalidad' => $solicitud->modalidad, 'equipo_id' => $equipment->id, 'estudiante_id' => $solicitud->estudiante_solicitante_id, 'horario_id' => $solicitud->horario_id, 'solicitud_id' => $solicitud->id, 'codigo_grupo' => $solicitud->codigo_grupo, 'origen' => 'administracion', 'autorizado_por' => auth()->id(), 'entregado_por' => auth()->id(), 'fecha_entrega' => now(), 'entrega_confirmada_at' => now(), 'fecha_devolucion_prevista' => $data['fecha_devolucion_prevista'], 'estado' => 'activa', 'estado_fisico_entrega' => $data['estado_fisico_entrega'], 'observaciones' => $solicitud->observaciones]);
            $solicitud->update(['estado' => 'aprobada', 'equipo_id' => $equipment->id, 'atendido_por' => auth()->id(), 'fecha_decision' => now()]);
            $equipment->activo()->update(['estado_uso' => 'asignado']);
        });
        $this->notifyStudent($solicitud, 'Asignación aprobada', 'Tu solicitud de equipo fue aprobada. Ya puedes revisar el préstamo en tu portal.');

        return back()->with('success', 'Solicitud aprobada y asignación creada correctamente.');
    }

    public function reject(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorizeRequest($solicitud);
        $data = $request->validate(['observaciones' => ['required', 'string', 'max:1000']]);
        if ($solicitud->estado !== 'pendiente') {
            return back()->withErrors(['solicitud' => 'La solicitud ya fue procesada.']);
        }
        $solicitud->update(['estado' => 'rechazada', 'observaciones' => $data['observaciones'], 'atendido_por' => auth()->id(), 'fecha_decision' => now()]);
        $this->notifyStudent($solicitud, 'Solicitud de equipo rechazada', 'Tu solicitud fue rechazada. Revisa el motivo en tu portal.');

        return back()->with('success', 'Solicitud rechazada correctamente.');
    }

    public function approveReturn(Request $request, Asignacion $asignacion): RedirectResponse
    {
        $this->authorizeAssignment($asignacion);
        $data = $request->validate(['estado_fisico_devolucion' => ['required', Rule::in(['bueno', 'regular', 'danado'])]]);
        if ($asignacion->estado !== 'devolucion_pendiente') {
            return back()->withErrors(['asignacion' => 'Esta devolución no está pendiente de autorización.']);
        }
        DB::transaction(function () use ($asignacion, $data): void {
            $asignacion->update(['estado' => 'devuelta', 'estado_fisico_devolucion' => $data['estado_fisico_devolucion'], 'fecha_devolucion_real' => now(), 'devolucion_recibida_por' => auth()->id(), 'devolucion_aprobada_por' => auth()->id()]);
            $asignacion->equipo?->activo()->update(['estado_uso' => 'disponible']);
        });
        $this->notifyStudent($asignacion->solicitud, 'Devolución aprobada', 'La devolución del equipo fue aprobada correctamente.');

        return back()->with('success', 'Devolución aprobada y equipo liberado.');
    }

    private function visibleRequests(): Builder
    {
        return Solicitud::query()->when($this->isTeacher(), fn (Builder $q) => $q->whereHas('horario', fn (Builder $h) => $h->where('docente_id', auth()->user()->docente_id)));
    }

    private function visibleAssignments(): Builder
    {
        return Asignacion::query()->when($this->isTeacher(), fn (Builder $q) => $q->whereHas('horario', fn (Builder $h) => $h->where('docente_id', auth()->user()->docente_id)));
    }

    private function isTeacher(): bool
    {
        return auth()->user()->tipo_usuario === 'docente';
    }

    private function authorizeRequest(Solicitud $solicitud): void
    {
        abort_unless(! $this->isTeacher() || (int) $solicitud->horario()->value('docente_id') === (int) auth()->user()->docente_id, 403);
    }

    private function authorizeAssignment(Asignacion $assignment): void
    {
        abort_unless(! $this->isTeacher() || (int) $assignment->horario()->value('docente_id') === (int) auth()->user()->docente_id, 403);
    }

    private function notifyStudent(?Solicitud $solicitud, string $title, string $message): void
    {
        $userId = $solicitud?->estudianteSolicitante?->user?->id;
        if ($userId) {
            Notificacion::create(['user_id' => $userId, 'tipo' => 'equipo', 'titulo' => $title, 'mensaje' => $message, 'url' => route('estudiante.loans.index')]);
        }
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'ASG-'.Str::upper(Str::random(8));
        } while (Asignacion::where('codigo', $code)->exists());

        return $code;
    }
}
