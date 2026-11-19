<?php

use App\Models\Activo;
use App\Models\Categoria;
use App\Models\Diagnostico;
use App\Models\Equipo;
use App\Models\Heartbeat;
use App\Models\Incidencia;

function createDeviceIncidentEquipment(string $suffix): Equipo
{
    $category = Categoria::create([
        'nombre' => 'Equipo de incidencias '.$suffix,
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => 'INC-'.$suffix,
        'categoria_id' => $category->id,
        'nombre' => 'Equipo de prueba '.$suffix,
        'estado_uso' => 'disponible',
    ]);

    return Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'DEVICE-INC-'.$suffix,
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
        'token_hash' => hash('sha256', 'device-token-'.$suffix),
    ]);
}

test('a linked device can report an incident that appears in the administration records', function () {
    $equipment = createDeviceIncidentEquipment('001');
    $otherEquipment = createDeviceIncidentEquipment('005');

    $this->withToken('device-token-001')
        ->postJson(route('api.v1.device.incidents.store'), [
            'tipo' => 'Hardware',
            'descripcion' => 'El ventilador hace un ruido irregular.',
            'prioridad' => 'alta',
            'equipo_id' => $otherEquipment->id,
        ])
        ->assertCreated()
        ->assertJsonPath('estado', 'registrada')
        ->assertJsonPath('incidencia.activo_id', $equipment->activo_id)
        ->assertJsonPath('incidencia.origen', 'app');

    $this->assertDatabaseHas('incidencias', [
        'activo_id' => $equipment->activo_id,
        'tipo' => 'Hardware',
        'prioridad' => 'alta',
        'estado' => 'abierta',
        'origen' => 'app',
        'reportado_por' => null,
    ]);
});

test('a device without an active credential cannot report an incident', function () {
    $equipment = createDeviceIncidentEquipment('002');
    $equipment->forceFill(['token_hash' => null])->save();

    $this->withToken('device-token-002')
        ->postJson(route('api.v1.device.incidents.store'), [
            'tipo' => 'Software',
            'descripcion' => 'La aplicación no inicia.',
        ])
        ->assertUnauthorized();

    $this->assertDatabaseCount('incidencias', 0);
});

test('duplicate active device incident reports return the existing record', function () {
    $equipment = createDeviceIncidentEquipment('003');
    $payload = [
        'tipo' => 'Red',
        'descripcion' => 'No hay acceso a la red institucional.',
    ];

    $this->withToken('device-token-003')
        ->postJson(route('api.v1.device.incidents.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('estado', 'registrada');

    $this->withToken('device-token-003')
        ->postJson(route('api.v1.device.incidents.store'), $payload)
        ->assertOk()
        ->assertJsonPath('estado', 'duplicada');

    $this->assertDatabaseCount('incidencias', 1);
    $this->assertDatabaseHas('incidencias', [
        'activo_id' => $equipment->activo_id,
        'origen' => 'app',
    ]);
});

test('the device panel returns server diagnostics and reported incidents', function () {
    $equipment = createDeviceIncidentEquipment('004');
    $heartbeat = Heartbeat::create([
        'equipo_id' => $equipment->id,
        'cpu_uso' => 96,
        'fecha_hora' => now(),
    ]);
    Diagnostico::create([
        'equipo_id' => $equipment->id,
        'heartbeat_id' => $heartbeat->id,
        'tipo' => 'CPU',
        'nivel' => 'advertencia',
        'codigo_regla' => 'CPU_ALTA',
        'descripcion' => 'Uso de CPU superior al 90%.',
        'fecha_hora' => now(),
    ]);
    Incidencia::create([
        'activo_id' => $equipment->activo_id,
        'tipo' => 'Hardware',
        'descripcion' => 'El ventilador requiere revisión.',
        'prioridad' => 'media',
        'estado' => 'abierta',
        'origen' => 'app',
        'fecha_reporte' => now(),
    ]);

    $this->withToken('device-token-004')
        ->getJson(route('api.v1.device.panel'))
        ->assertOk()
        ->assertJsonPath('diagnosticos.0.codigo_regla', 'CPU_ALTA')
        ->assertJsonPath('incidencias.0.tipo', 'Hardware')
        ->assertJsonPath('incidencias.0.origen', 'app');
});
