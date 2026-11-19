<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipo;
use App\Models\Incidencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeviceIncidentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['Hardware', 'Software', 'Red', 'Bateria', 'Pantalla', 'Periferico', 'Otro'])],
            'descripcion' => ['required', 'string', 'max:5000'],
            'prioridad' => ['sometimes', Rule::in(['baja', 'media', 'alta', 'critica'])],
        ]);
        $token = $request->bearerToken() ?: $request->header('X-ITIBB-Device-Token');
        $equipo = $token === null || $token === ''
            ? null
            : Equipo::query()->authorizedByDeviceToken($token)->first();

        if ($equipo === null) {
            return response()->json(['mensaje' => 'Dispositivo no autorizado.'], 401);
        }

        $resultado = DB::transaction(function () use ($datos, $equipo): array {
            $equipoBloqueado = Equipo::query()->whereKey($equipo->id)->lockForUpdate()->firstOrFail();
            $estadosAbiertos = ['abierta', 'en_revision'];
            $existente = Incidencia::query()
                ->where('activo_id', $equipoBloqueado->activo_id)
                ->where('tipo', $datos['tipo'])
                ->whereIn('estado', $estadosAbiertos)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                return ['incidencia' => $existente, 'duplicada' => true];
            }

            $asignacion = $equipoBloqueado->asignaciones()
                ->whereIn('estado', [
                    'pendiente_entrega',
                    'activa',
                    'devolucion_pendiente',
                    'vencida',
                    'con_incidencia',
                ])
                ->latest('fecha_entrega')
                ->first();
            $incidencia = Incidencia::create([
                'activo_id' => $equipoBloqueado->activo_id,
                'asignacion_id' => $asignacion?->id,
                'tipo' => $datos['tipo'],
                'descripcion' => $datos['descripcion'],
                'prioridad' => $datos['prioridad'] ?? 'media',
                'estado' => 'abierta',
                'origen' => 'app',
                'fecha_reporte' => now(),
            ]);

            return ['incidencia' => $incidencia, 'duplicada' => false];
        });

        return response()->json([
            'estado' => $resultado['duplicada'] ? 'duplicada' : 'registrada',
            'incidencia' => $resultado['incidencia'],
        ], $resultado['duplicada'] ? 200 : 201);
    }
}
