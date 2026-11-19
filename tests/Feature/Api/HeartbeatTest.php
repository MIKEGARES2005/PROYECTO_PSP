<?php

use App\Models\Activo;
use App\Models\Categoria;
use App\Models\Complemento;
use App\Models\Diagnostico;
use App\Models\Equipo;
use App\Models\Heartbeat;

test('a registered device can send a heartbeat and trigger diagnostics', function () {
    $category = Categoria::create([
        'nombre' => 'Equipos API',
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => 'API-001',
        'categoria_id' => $category->id,
        'nombre' => 'PC API',
        'estado_uso' => 'disponible',
    ]);
    $token = 'token-pc-prueba';
    $equipment = Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'PC-API-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
        'token_hash' => hash('sha256', $token),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.v1.heartbeats.store'), [
            'equipo_id' => $equipment->id,
            'uuid' => '7b4f2c6e-2b80-4c79-9e31-3d8e7db88731',
            'cpu_uso' => 95,
            'ram_uso' => 42,
            'almacenamiento_uso' => 40,
            'temperatura' => 82,
        ]);

    $response->assertCreated()
        ->assertJsonPath('estado', 'recibido')
        ->assertJsonPath('equipo_id', $equipment->id);

    expect(Heartbeat::count())->toBe(1)
        ->and(Diagnostico::where('codigo_regla', 'CPU_ALTA')->exists())->toBeTrue()
        ->and($equipment->fresh()->ultima_conexion)->not->toBeNull();
});

test('heartbeats from both a PC and tablet trigger their device diagnostic rules', function () {
    $category = Categoria::create([
        'nombre' => 'Equipos con diagnóstico automático',
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $pcAsset = Activo::create([
        'codigo' => 'API-DIAG-PC',
        'categoria_id' => $category->id,
        'nombre' => 'PC con diagnóstico',
        'estado_uso' => 'disponible',
    ]);
    $pcToken = 'token-pc-diagnostico';
    $pc = Equipo::create([
        'activo_id' => $pcAsset->id,
        'identificador' => 'PC-DIAG-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
        'token_hash' => hash('sha256', $pcToken),
    ]);
    $tabletAsset = Activo::create([
        'codigo' => 'API-DIAG-TAB',
        'categoria_id' => $category->id,
        'nombre' => 'Tablet con diagnóstico',
        'estado_uso' => 'disponible',
    ]);
    $tabletToken = 'token-tablet-diagnostico';
    $tablet = Equipo::create([
        'activo_id' => $tabletAsset->id,
        'identificador' => 'TAB-DIAG-001',
        'tipo_equipo' => 'tablet',
        'modo_uso' => 'individual',
        'token_hash' => hash('sha256', $tabletToken),
    ]);

    $this->withToken($pcToken)
        ->postJson(route('api.v1.heartbeats.store'), [
            'uuid' => 'd6d3ad1e-02e4-4f41-893a-53d7458b4cf1',
            'cpu_uso' => 95,
            'ram_uso' => 70,
            'almacenamiento_uso' => 40,
            'metricas' => ['cliente' => 'agente_pc'],
        ])
        ->assertCreated()
        ->assertJsonPath('equipo_id', $pc->id);

    $this->withToken($tabletToken)
        ->postJson(route('api.v1.heartbeats.store'), [
            'uuid' => '7d1a881f-3906-4c72-85c1-e26951b26de9',
            'ram_uso' => 70,
            'almacenamiento_uso' => 40,
            'bateria' => 10,
            'temperatura' => 82,
            'cargando' => false,
            'metricas' => ['cliente' => 'prueba_flutter'],
        ])
        ->assertCreated()
        ->assertJsonPath('equipo_id', $tablet->id);

    $this->assertDatabaseHas('diagnosticos', [
        'equipo_id' => $pc->id,
        'codigo_regla' => 'CPU_ALTA',
    ]);
    $this->assertDatabaseHas('diagnosticos', [
        'equipo_id' => $tablet->id,
        'codigo_regla' => 'BATERIA_BAJA',
    ]);
    $this->assertDatabaseHas('diagnosticos', [
        'equipo_id' => $tablet->id,
        'codigo_regla' => 'TEMPERATURA_CRITICA',
    ]);
});

test('the heartbeat endpoint rejects an invalid device token and duplicates by uuid', function () {
    $invalid = $this->withHeader('Authorization', 'Bearer invalido')
        ->postJson(route('api.v1.heartbeats.store'), []);

    $invalid->assertUnauthorized();

    $category = Categoria::create([
        'nombre' => 'Equipos API 2',
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => 'API-002',
        'categoria_id' => $category->id,
        'nombre' => 'Tablet API',
        'estado_uso' => 'disponible',
    ]);
    $token = 'token-tablet-prueba';
    $equipment = Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'TAB-API-001',
        'tipo_equipo' => 'tablet',
        'modo_uso' => 'individual',
        'token_hash' => hash('sha256', $token),
    ]);
    $payload = [
        'equipo_id' => $equipment->id,
        'uuid' => '8b4f2c6e-2b80-4c79-9e31-3d8e7db88732',
    ];

    $this->withToken($token)->postJson(route('api.v1.heartbeats.store'), $payload)->assertCreated();
    $this->withToken($token)
        ->postJson(route('api.v1.heartbeats.store'), $payload)
        ->assertOk()
        ->assertJsonPath('estado', 'duplicado');
});

test('a heartbeat UUID already owned by another device is rejected', function () {
    $firstCategory = Categoria::create([
        'nombre' => 'Equipo UUID primero',
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $firstAsset = Activo::create([
        'codigo' => 'UUID-001',
        'categoria_id' => $firstCategory->id,
        'nombre' => 'Primer equipo UUID',
        'estado_uso' => 'disponible',
    ]);
    $firstEquipment = Equipo::create([
        'activo_id' => $firstAsset->id,
        'identificador' => 'UUID-EQ-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
        'token_hash' => hash('sha256', 'uuid-first-token'),
    ]);
    $secondCategory = Categoria::create([
        'nombre' => 'Equipo UUID segundo',
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $secondAsset = Activo::create([
        'codigo' => 'UUID-002',
        'categoria_id' => $secondCategory->id,
        'nombre' => 'Segundo equipo UUID',
        'estado_uso' => 'disponible',
    ]);
    Equipo::create([
        'activo_id' => $secondAsset->id,
        'identificador' => 'UUID-EQ-002',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
        'token_hash' => hash('sha256', 'uuid-second-token'),
    ]);
    $payload = [
        'uuid' => '103f4bca-2dd0-4c81-a81a-73d4629c5e1a',
        'cpu_uso' => 42,
    ];

    $this->withToken('uuid-first-token')
        ->postJson(route('api.v1.heartbeats.store'), $payload)
        ->assertCreated();

    $this->withToken('uuid-second-token')
        ->postJson(route('api.v1.heartbeats.store'), $payload)
        ->assertConflict();

    expect(Heartbeat::count())->toBe(1)
        ->and(Heartbeat::first()->equipo_id)->toBe($firstEquipment->id);
});

test('the device panel returns current equipment and linked complement details', function () {
    $equipmentCategory = Categoria::create([
        'nombre' => 'Equipos panel QR',
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $complementCategory = Categoria::create([
        'nombre' => 'Complementos panel QR',
        'grupo' => 'electronico',
        'estado' => 'activa',
    ]);
    $equipmentAsset = Activo::create([
        'codigo' => 'PANEL-EQ-001',
        'categoria_id' => $equipmentCategory->id,
        'nombre' => 'Tablet de monitoreo',
        'marca' => 'TLC',
        'modelo' => 'TAB10L',
        'estado_uso' => 'disponible',
    ]);
    $token = 'token-panel-prueba';
    $equipment = Equipo::create([
        'activo_id' => $equipmentAsset->id,
        'identificador' => 'TAB-PANEL-001',
        'tipo_equipo' => 'tablet',
        'modo_uso' => 'individual',
        'token_hash' => hash('sha256', $token),
    ]);
    $complementAsset = Activo::create([
        'codigo' => 'PANEL-COMP-001',
        'categoria_id' => $complementCategory->id,
        'nombre' => 'Teclado',
        'estado_uso' => 'disponible',
    ]);
    $complement = Complemento::create([
        'activo_id' => $complementAsset->id,
        'tipo_complemento' => 'Teclado USB',
        'estado_fisico' => 'bueno',
    ]);
    $equipment->complementos()->attach($complement->id, [
        'fecha_inicio' => now(),
    ]);

    $this->withToken($token)
        ->getJson(route('api.v1.device.panel', ['equipo_id' => $equipment->id]))
        ->assertOk()
        ->assertJsonPath('equipo.nombre', 'Tablet de monitoreo')
        ->assertJsonPath('equipo.marca', 'TLC')
        ->assertJsonPath('complementos.0.nombre', 'Teclado')
        ->assertJsonPath('complementos.0.tipo', 'Teclado USB')
        ->assertJsonPath('complementos.0.codigo', 'PANEL-COMP-001');
});
