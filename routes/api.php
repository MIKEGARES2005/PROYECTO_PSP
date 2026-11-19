<?php

use App\Http\Controllers\Api\DeviceIncidentController;
use App\Http\Controllers\Api\DevicePairingController;
use App\Http\Controllers\Api\EstadoServidorController;
use App\Http\Controllers\Api\HeartbeatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| Consumidas por la aplicación Flutter "Monitor Local" y otros clientes
| externos. Se registran en bootstrap/app.php bajo el prefijo /api.
|
*/

Route::get('/estado-servidor', EstadoServidorController::class)
    ->name('api.estado-servidor');

Route::post('/v1/device/pair/preview', [DevicePairingController::class, 'preview'])
    ->middleware('throttle:10,1')
    ->name('api.v1.device.pair.preview');

Route::post('/v1/device/pair', [DevicePairingController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.v1.device.pair');

Route::post('/v1/heartbeats', [HeartbeatController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('api.v1.heartbeats.store');

Route::post('/v1/device/incidents', [DeviceIncidentController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('api.v1.device.incidents.store');

Route::get('/v1/device/status', [HeartbeatController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('api.v1.device.status');

Route::get('/v1/device/panel', [HeartbeatController::class, 'panel'])
    ->middleware('throttle:120,1')
    ->name('api.v1.device.panel');
