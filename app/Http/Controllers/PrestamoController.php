<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Equipo;
use App\Models\Invitado;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrestamoController extends Controller
{
    public function create(): View
    {
        $equipos = Equipo::query()
            ->with(['activo', 'ultimoHeartbeat'])
            ->whereHas('activo', fn (Builder $query) => $query->where('estado_uso', 'disponible'))
            ->whereDoesntHave('asignaciones', fn (Builder $query) => $query->whereIn('estado', ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia']))
            ->orderBy('identificador')
            ->get();
        $invitados = Invitado::query()->where('estado', 'activo')->orderBy('nombres')->orderBy('apellido_paterno')->get();

        return view('prestamos.create', compact('equipos', 'invitados'));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'equipo_id' => ['required', 'exists:equipos,id'],
            'invitado_id' => ['required', 'exists:invitados,id'],
            'fecha_entrega' => ['required', 'date'],
            'fecha_devolucion_prevista' => ['required', 'date', 'after_or_equal:fecha_entrega'],
            'estado_fisico_entrega' => ['required', 'in:bueno,regular,danado'],
            'observaciones' => ['nullable', 'string'],
        ]);
        $equipo = Equipo::with('activo')->findOrFail($datos['equipo_id']);
        if ($equipo->modo_uso !== 'individual' || $equipo->activo?->estado_uso !== 'disponible') {
            return back()->withInput()->withErrors(['equipo_id' => 'Los préstamos a invitados requieren un equipo individual disponible.']);
        }
        if ($equipo->asignaciones()->whereIn('estado', ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia'])->exists()) {
            return back()->withInput()->withErrors(['equipo_id' => 'Este equipo ya tiene un movimiento activo.']);
        }

        $datos['codigo'] = $this->codigoUnico();
        $datos['tipo'] = 'prestado';
        $datos['modalidad'] = 'individual';
        $datos['autorizado_por'] = auth()->id();
        $datos['entregado_por'] = auth()->id();
        $datos['entrega_confirmada_at'] = now();
        $datos['estado'] = 'activa';

        DB::transaction(function () use ($datos, $equipo): void {
            Asignacion::create($datos);
            $equipo->activo()->update(['estado_uso' => 'asignado']);
        });

        return redirect()->route('asignaciones.index')->with('success', 'Préstamo registrado correctamente.');
    }

    private function codigoUnico(): string
    {
        do {
            $codigo = 'PRE-'.Str::upper(Str::random(8));
        } while (Asignacion::where('codigo', $codigo)->exists());

        return $codigo;
    }
}
