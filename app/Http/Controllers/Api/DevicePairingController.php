<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DevicePairingController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $request->merge([
            'codigo' => Str::upper(trim((string) $request->input('codigo'))),
        ]);
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'alpha_num', 'size:12'],
            'tipo' => ['required', 'string', 'in:cpu,tablet'],
        ]);

        $equipo = Equipo::query()
            ->with('activo:id,codigo,nombre')
            ->where('codigo_vinculacion_hash', hash('sha256', $datos['codigo']))
            ->where('codigo_vinculacion_expira_en', '>', now())
            ->first();

        if ($equipo === null) {
            return response()->json([
                'mensaje' => 'El código de vinculación no existe, ya fue utilizado o venció.',
            ], 401);
        }

        if ($equipo->tipo_equipo !== $datos['tipo']) {
            return response()->json([
                'mensaje' => 'El código pertenece a un tipo de equipo diferente a esta aplicación.',
            ], 422);
        }

        return response()->json([
            'equipo' => [
                'id' => $equipo->id,
                'identificador' => $equipo->identificador,
                'tipo' => $equipo->tipo_equipo,
                'codigo' => $equipo->activo?->codigo,
                'nombre' => $equipo->activo?->nombre,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'codigo' => Str::upper(trim((string) $request->input('codigo'))),
        ]);
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'alpha_num', 'size:12'],
            'tipo' => ['required', 'string', 'in:cpu,tablet'],
            'equipo_id' => ['sometimes', 'integer', 'exists:equipos,id'],
        ]);

        $vinculacion = DB::transaction(function () use ($datos): ?array {
            $equipo = Equipo::query()
                ->where('codigo_vinculacion_hash', hash('sha256', $datos['codigo']))
                ->where('codigo_vinculacion_expira_en', '>', now())
                ->lockForUpdate()
                ->first();

            if ($equipo === null) {
                return null;
            }

            if (
                isset($datos['equipo_id'])
                && $equipo->id !== (int) $datos['equipo_id']
            ) {
                return ['equipo_no_coincide' => true];
            }

            if ($equipo->tipo_equipo !== $datos['tipo']) {
                return ['tipo_incorrecto' => true];
            }

            $token = Str::random(64);
            $equipo->forceFill([
                'token_hash' => hash('sha256', $token),
                'token_creado_en' => now(),
                'token_expira_en' => null,
                'codigo_vinculacion_hash' => null,
                'codigo_vinculacion_expira_en' => null,
            ])->save();
            $equipo->load('activo');

            return [
                'token' => $token,
                'equipo' => [
                    'id' => $equipo->id,
                    'identificador' => $equipo->identificador,
                    'tipo' => $equipo->tipo_equipo,
                    'codigo' => $equipo->activo?->codigo,
                    'nombre' => $equipo->activo?->nombre,
                ],
            ];
        });

        if ($vinculacion === null) {
            return response()->json([
                'mensaje' => 'El código de vinculación no existe, ya fue utilizado o venció.',
            ], 401);
        }

        if (isset($vinculacion['tipo_incorrecto'])) {
            return response()->json([
                'mensaje' => 'El código pertenece a un tipo de equipo diferente a esta aplicación.',
            ], 422);
        }

        if (isset($vinculacion['equipo_no_coincide'])) {
            return response()->json([
                'mensaje' => 'El equipo asociado al código cambió. Vuelve a verificar el código antes de vincular.',
            ], 409);
        }

        return response()->json($vinculacion, 201);
    }
}
