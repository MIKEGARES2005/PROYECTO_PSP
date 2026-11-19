<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class EstadoServidorController extends Controller
{
    /**
     * Comprobar que el servidor Laravel está operativo.
     *
     * Lo consume la aplicación Flutter "Monitor Local" para validar
     * la conexión antes de solicitar datos de monitoreo.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'estado' => 'ok',
            'mensaje' => 'Servidor Laravel operativo',
            'servidor' => config('app.name'),
            'version_laravel' => app()->version(),
            'hora' => now()->toIso8601String(),
        ]);
    }
}
