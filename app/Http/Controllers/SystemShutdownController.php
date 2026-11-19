<?php

namespace App\Http\Controllers;

use App\Services\SystemShutdownService;
use Illuminate\Http\JsonResponse;

class SystemShutdownController extends Controller
{
    public function __invoke(SystemShutdownService $systemShutdown): JsonResponse
    {
        if (! $systemShutdown->isAgentAvailable()) {
            return response()->json([
                'message' => 'El ayudante local de finalización no está disponible. Inicia el sistema desde el acceso directo.',
            ], 503);
        }

        if (! $systemShutdown->requestShutdown()) {
            return response()->json([
                'message' => 'La finalización del sistema ya está en curso.',
            ], 409);
        }

        return response()->json([
            'message' => 'Solicitud recibida. El sistema se está finalizando.',
        ], 202);
    }
}
