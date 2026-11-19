<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Models\CodigoQr;
use App\Models\Diagnostico;
use App\Models\Equipo;
use App\Models\Heartbeat;
use App\Models\Incidencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HeartbeatController extends Controller
{
    public function panel(Request $request): JsonResponse
    {
        $equipo = $this->resolveEquipment(
            $request->bearerToken() ?: $request->header('X-ITIBB-Device-Token'),
            $request->integer('equipo_id') ?: null,
        );

        if ($equipo === null) {
            return response()->json(['mensaje' => 'Dispositivo no autorizado.'], 401);
        }

        $equipo->load(['activo', 'ultimoHeartbeat']);
        $complementos = $equipo->complementos()
            ->wherePivotNull('fecha_fin')
            ->with('activo')
            ->get();
        $asignaciones = $equipo->asignaciones()
            ->with(['estudiante', 'invitado', 'horario.materia', 'horario.docente'])
            ->whereIn('estado', ['pendiente_entrega', 'activa', 'devolucion_pendiente', 'vencida', 'con_incidencia'])
            ->latest()
            ->get();
        $horarioIds = $asignaciones->pluck('horario_id')->filter();
        $codigosQr = CodigoQr::query()
            ->where('activo', true)
            ->where(function ($query) use ($equipo, $horarioIds): void {
                $query->where('activo_id', $equipo->activo_id)
                    ->orWhereIn('horario_id', $horarioIds);
            })
            ->latest()
            ->get()
            ->map(fn (CodigoQr $codigo): array => [
                'id' => $codigo->id,
                'tipo' => $codigo->tipo,
                'activo' => $codigo->activo,
                'fecha_uso' => $codigo->fecha_uso?->toDateString(),
                'expira_en' => $codigo->expira_en?->toIso8601String(),
                'activo_id' => $codigo->activo_id,
                'horario_id' => $codigo->horario_id,
            ]);

        $diagnosticos = $equipo->diagnosticos()
            ->latest('fecha_hora')
            ->limit(10)
            ->get(['id', 'tipo', 'nivel', 'codigo_regla', 'descripcion', 'fecha_hora']);
        $incidencias = Incidencia::query()
            ->where('activo_id', $equipo->activo_id)
            ->latest('fecha_reporte')
            ->limit(10)
            ->get(['id', 'tipo', 'descripcion', 'prioridad', 'estado', 'origen', 'fecha_reporte']);

        return response()->json([
            'estado' => 'ok',
            'equipo' => [
                'id' => $equipo->id,
                'identificador' => $equipo->identificador,
                'tipo' => $equipo->tipo_equipo,
                'codigo' => $equipo->activo?->codigo,
                'nombre' => $equipo->activo?->nombre,
                'marca' => $equipo->activo?->marca,
                'modelo' => $equipo->activo?->modelo,
                'estado' => $equipo->activo?->estado_uso,
            ],
            'complementos' => $complementos->map(fn ($complemento): array => [
                'id' => $complemento->id,
                'tipo' => $complemento->tipo_complemento,
                'estado_fisico' => $complemento->estado_fisico,
                'codigo' => $complemento->activo?->codigo,
                'nombre' => $complemento->activo?->nombre,
                'marca' => $complemento->activo?->marca,
                'modelo' => $complemento->activo?->modelo,
            ])->values(),
            'heartbeat' => $equipo->ultimoHeartbeat?->only([
                'cpu_uso',
                'ram_uso',
                'almacenamiento_uso',
                'bateria',
                'temperatura',
                'cargando',
                'ip',
                'fecha_hora',
            ]),
            'asignaciones' => $asignaciones->map(fn ($asignacion): array => [
                'id' => $asignacion->id,
                'codigo' => $asignacion->codigo,
                'tipo' => $asignacion->tipo,
                'modalidad' => $asignacion->modalidad,
                'estado' => $asignacion->estado,
                'estudiante' => $asignacion->estudiante?->nombreCompleto(),
                'invitado' => $asignacion->invitado?->nombreCompleto(),
                'materia' => $asignacion->horario?->materia?->nombre,
                'docente' => $asignacion->horario?->docente?->nombreCompleto(),
                'aula' => $asignacion->horario?->aula,
                'devolucion_prevista' => $asignacion->fecha_devolucion_prevista?->toIso8601String(),
            ]),
            'codigos_qr' => $codigosQr,
            'diagnosticos' => $diagnosticos,
            'incidencias' => $incidencias,
            'generado_en' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $equipo = $this->resolveEquipment(
            $request->bearerToken() ?: $request->header('X-ITIBB-Device-Token'),
            $request->integer('equipo_id') ?: null,
        );

        if ($equipo === null) {
            return response()->json(['mensaje' => 'Dispositivo no autorizado.'], 401);
        }

        $heartbeat = $equipo->ultimoHeartbeat;

        return response()->json([
            'estado' => 'ok',
            'equipo' => [
                'id' => $equipo->id,
                'identificador' => $equipo->identificador,
                'tipo' => $equipo->tipo_equipo,
                'ultima_conexion' => $equipo->ultima_conexion?->toIso8601String(),
            ],
            'heartbeat' => $heartbeat?->only([
                'id',
                'cpu_uso',
                'ram_uso',
                'almacenamiento_uso',
                'bateria',
                'temperatura',
                'cargando',
                'ip',
                'fecha_hora',
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'equipo_id' => ['nullable', 'integer', 'exists:equipos,id'],
            'uuid' => ['nullable', 'uuid'],
            'cpu_uso' => ['nullable', 'numeric', 'between:0,100'],
            'ram_uso' => ['nullable', 'numeric', 'between:0,100'],
            'almacenamiento_uso' => ['nullable', 'numeric', 'between:0,100'],
            'bateria' => ['nullable', 'numeric', 'between:0,100'],
            'temperatura' => ['nullable', 'numeric', 'between:-50,150'],
            'cargando' => ['nullable', 'boolean'],
            'ip' => ['nullable', 'ip'],
            'metricas' => ['nullable', 'array'],
        ]);

        $token = $request->bearerToken() ?: $request->header('X-ITIBB-Device-Token');
        $equipo = $this->resolveEquipment($token, $datos['equipo_id'] ?? null);

        if ($equipo === null) {
            return response()->json(['mensaje' => 'Dispositivo no autorizado.'], 401);
        }

        $cliente = data_get($datos, 'metricas.cliente');
        if (($cliente === 'agente_pc' && $equipo->tipo_equipo !== 'cpu')
            || ($cliente === 'prueba_flutter' && $equipo->tipo_equipo !== 'tablet')) {
            return response()->json([
                'mensaje' => 'El tipo de dispositivo no coincide con el equipo vinculado.',
            ], 422);
        }

        if (($datos['uuid'] ?? null) !== null) {
            $existente = Heartbeat::query()->where('uuid', $datos['uuid'])->first();

            if ($existente !== null) {
                if ($existente->equipo_id !== $equipo->id) {
                    return response()->json([
                        'mensaje' => 'El identificador de reporte ya fue utilizado por otro equipo.',
                    ], 409);
                }

                return response()->json([
                    'estado' => 'duplicado',
                    'heartbeat_id' => $existente->id,
                    'equipo_id' => $existente->equipo_id,
                ]);
            }
        }

        $heartbeat = DB::transaction(function () use ($datos, $equipo): Heartbeat {
            $heartbeat = Heartbeat::create([
                ...$datos,
                'equipo_id' => $equipo->id,
                'uuid' => $datos['uuid'] ?? (string) Str::uuid(),
                'ip' => $datos['ip'] ?? request()->ip(),
                'fecha_hora' => now(),
            ]);

            $equipo->update([
                'ultima_conexion' => $heartbeat->fecha_hora,
                'ip_ultima' => $heartbeat->ip,
            ]);

            $this->evaluateRules($heartbeat);

            return $heartbeat;
        });

        return response()->json([
            'estado' => 'recibido',
            'heartbeat_id' => $heartbeat->id,
            'equipo_id' => $equipo->id,
            'recibido_en' => $heartbeat->fecha_hora?->toIso8601String(),
        ], 201);
    }

    private function resolveEquipment(?string $token, ?int $equipmentId): ?Equipo
    {
        if ($token === null || $token === '') {
            return null;
        }

        $query = Equipo::query()->authorizedByDeviceToken($token);

        if ($equipmentId !== null) {
            $query->whereKey($equipmentId);
        }

        return $query->first();
    }

    private function evaluateRules(Heartbeat $heartbeat): void
    {
        $rules = [
            ['campo' => 'cpu_uso', 'umbral' => 90, 'codigo' => 'CPU_ALTA', 'tipo' => 'CPU', 'nivel' => 'advertencia', 'mensaje' => 'Uso de CPU superior al 90%.'],
            ['campo' => 'ram_uso', 'umbral' => 90, 'codigo' => 'RAM_ALTA', 'tipo' => 'RAM', 'nivel' => 'advertencia', 'mensaje' => 'Uso de RAM superior al 90%.'],
            ['campo' => 'almacenamiento_uso', 'umbral' => 90, 'codigo' => 'DISCO_ALTO', 'tipo' => 'Almacenamiento', 'nivel' => 'advertencia', 'mensaje' => 'Almacenamiento ocupado por encima del 90%.'],
            ['campo' => 'temperatura', 'umbral' => 80, 'codigo' => 'TEMPERATURA_CRITICA', 'tipo' => 'Temperatura', 'nivel' => 'critica', 'mensaje' => 'Temperatura superior a 80 °C.'],
            ['campo' => 'bateria', 'umbral' => 15, 'codigo' => 'BATERIA_BAJA', 'tipo' => 'Batería', 'nivel' => 'advertencia', 'mensaje' => 'Batería por debajo del 15%.'],
        ];

        foreach ($rules as $rule) {
            $value = $heartbeat->{$rule['campo']};
            $triggered = $rule['codigo'] === 'BATERIA_BAJA'
                ? $value !== null && $value <= $rule['umbral'] && ! $heartbeat->cargando
                : $value !== null && $value >= $rule['umbral'];

            if (! $triggered) {
                continue;
            }

            Diagnostico::create([
                'equipo_id' => $heartbeat->equipo_id,
                'heartbeat_id' => $heartbeat->id,
                'tipo' => $rule['tipo'],
                'nivel' => $rule['nivel'],
                'codigo_regla' => $rule['codigo'],
                'descripcion' => $rule['mensaje'],
                'fecha_hora' => $heartbeat->fecha_hora,
            ]);

            Alerta::query()->firstOrCreate(
                ['equipo_id' => $heartbeat->equipo_id, 'codigo_regla' => $rule['codigo'], 'estado' => 'abierta'],
                ['severidad' => $rule['nivel'], 'mensaje' => $rule['mensaje'], 'abierta_en' => $heartbeat->fecha_hora]
            );
        }
    }
}
