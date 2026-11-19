<?php

use App\Models\Activo;
use App\Models\Categoria;
use App\Models\Docente;
use App\Models\Equipo;
use App\Models\User;

function createPairingEquipment(string $code): Equipo
{
    $category = Categoria::create([
        'nombre' => 'Equipos para vinculación '.$code,
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => $code,
        'categoria_id' => $category->id,
        'nombre' => 'Equipo '.$code,
        'estado_uso' => 'disponible',
    ]);

    return Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => $code,
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
}

test('an administrator can pair a selected equipment with a one-time code', function () {
    $equipment = createPairingEquipment('PAIR-001');
    $administrator = User::factory()->administrador()->create();

    $this->actingAs($administrator)
        ->get(route('monitoreo.index'))
        ->assertOk()
        ->assertSeeText('Elige la PC o tablet que vas a vincular')
        ->assertSeeText('PAIR-001')
        ->assertSeeText('Vincular este dispositivo');

    $this->actingAs($administrator)
        ->from(route('monitoreo.index'))
        ->post(route('monitoreo.equipos.vinculacion.store', $equipment))
        ->assertRedirect(route('monitoreo.index'))
        ->assertSessionHas('pairing_code');

    $pairingCode = session('pairing_code.codigo');

    expect($pairingCode)->toBeString()->toHaveLength(12);
    expect($equipment->fresh()->codigo_vinculacion_hash)->toBe(hash('sha256', $pairingCode));

    $this->actingAs($administrator)
        ->get(route('monitoreo.index'))
        ->assertOk()
        ->assertSeeText('No ingreses el código patrimonial')
        ->assertSeeText('Código de vinculación para PAIR-001 · activo PAIR-001')
        ->assertSeeText($pairingCode);

    $pairResponse = $this->postJson(route('api.v1.device.pair'), [
        'codigo' => strtolower($pairingCode),
        'tipo' => 'cpu',
    ]);

    $pairResponse->assertCreated()
        ->assertJsonPath('equipo.id', $equipment->id)
        ->assertJsonPath('equipo.tipo', 'cpu');

    $token = $pairResponse->json('token');

    expect($token)->toBeString()->toHaveLength(64);
    expect($equipment->fresh()->token_hash)->toBe(hash('sha256', $token));
    expect($equipment->fresh()->codigo_vinculacion_hash)->toBeNull();

    $this->withToken($token)
        ->postJson(route('api.v1.heartbeats.store'), ['cpu_uso' => 25])
        ->assertCreated()
        ->assertJsonPath('equipo_id', $equipment->id);
});

test('a pairing code previews its selected equipment without consuming the code', function () {
    $equipment = createPairingEquipment('PAIR-PREVIEW');
    $equipment->forceFill([
        'codigo_vinculacion_hash' => hash('sha256', 'PREVIEW12345'),
        'codigo_vinculacion_expira_en' => now()->addMinutes(10),
    ])->save();

    $this->postJson(route('api.v1.device.pair.preview'), [
        'codigo' => 'preview12345',
        'tipo' => 'cpu',
    ])->assertOk()
        ->assertJsonPath('equipo.id', $equipment->id)
        ->assertJsonPath('equipo.identificador', 'PAIR-PREVIEW')
        ->assertJsonPath('equipo.nombre', 'Equipo PAIR-PREVIEW')
        ->assertJsonMissingPath('token');

    expect($equipment->fresh()->codigo_vinculacion_hash)->toBe(hash('sha256', 'PREVIEW12345'));

    $this->postJson(route('api.v1.device.pair'), [
        'codigo' => 'PREVIEW12345',
        'tipo' => 'cpu',
        'equipo_id' => $equipment->id,
    ])->assertCreated()
        ->assertJsonPath('equipo.id', $equipment->id);
});

test('an administrator with a teacher profile can still see every equipment to pair', function () {
    $equipment = createPairingEquipment('PAIR-ADMIN-DOCENTE');
    $teacherProfile = Docente::create([
        'nombres' => 'Administrador',
        'apellido_paterno' => 'Docente',
        'ci' => 'PAIR-ADMIN-DOCENTE',
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    $administrator = User::factory()->administrador()->create([
        'docente_id' => $teacherProfile->id,
    ]);

    $this->actingAs($administrator)
        ->get(route('monitoreo.index'))
        ->assertOk()
        ->assertSeeText($equipment->identificador)
        ->assertViewHas('estadisticas', fn (array $stats): bool => $stats['total'] === 1);
});

test('a pairing code cannot link a different equipment than the one previewed', function () {
    $equipment = createPairingEquipment('PAIR-CONFIRM');
    $differentEquipment = createPairingEquipment('PAIR-OTHER');
    $equipment->forceFill([
        'codigo_vinculacion_hash' => hash('sha256', 'CONFIRM12345'),
        'codigo_vinculacion_expira_en' => now()->addMinutes(10),
    ])->save();

    $this->postJson(route('api.v1.device.pair'), [
        'codigo' => 'CONFIRM12345',
        'tipo' => 'cpu',
        'equipo_id' => $differentEquipment->id,
    ])->assertStatus(409)
        ->assertJsonPath('mensaje', 'El equipo asociado al código cambió. Vuelve a verificar el código antes de vincular.');

    expect($equipment->fresh()->codigo_vinculacion_hash)->toBe(hash('sha256', 'CONFIRM12345'));
    expect($equipment->fresh()->token_hash)->toBeNull();
});

test('a pairing code expires and cannot be reused', function () {
    $expiredEquipment = createPairingEquipment('PAIR-002');
    $expiredEquipment->forceFill([
        'codigo_vinculacion_hash' => hash('sha256', 'EXPIRED12345'),
        'codigo_vinculacion_expira_en' => now()->subSecond(),
    ])->save();
    $activeEquipment = createPairingEquipment('PAIR-003');
    $activeEquipment->forceFill([
        'codigo_vinculacion_hash' => hash('sha256', 'ACTIVE123456'),
        'codigo_vinculacion_expira_en' => now()->addMinutes(10),
    ])->save();

    $this->postJson(route('api.v1.device.pair'), ['codigo' => 'EXPIRED12345', 'tipo' => 'cpu'])
        ->assertUnauthorized();

    $firstPairing = $this->postJson(route('api.v1.device.pair'), ['codigo' => 'ACTIVE123456', 'tipo' => 'cpu']);
    $firstPairing->assertCreated();

    $this->postJson(route('api.v1.device.pair'), ['codigo' => 'ACTIVE123456', 'tipo' => 'cpu'])
        ->assertUnauthorized();

    expect($activeEquipment->fresh()->token_hash)->toBe(hash('sha256', $firstPairing->json('token')));
});

test('a teacher cannot create a device pairing code', function () {
    $equipment = createPairingEquipment('PAIR-004');
    $teacherProfile = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Prueba',
        'ci' => 'PAIR-TEACHER-001',
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    $teacher = User::factory()->docente($teacherProfile)->create();

    $this->actingAs($teacher)
        ->post(route('monitoreo.equipos.vinculacion.store', $equipment))
        ->assertForbidden();

    expect($equipment->fresh()->codigo_vinculacion_hash)->toBeNull();
});

test('an administrator can revoke a paired device credential', function () {
    $equipment = createPairingEquipment('PAIR-005');
    $administrator = User::factory()->administrador()->create();
    $equipment->forceFill(['token_hash' => hash('sha256', 'revocable-device-token')])->save();

    $this->actingAs($administrator)
        ->delete(route('monitoreo.equipos.vinculacion.destroy', $equipment))
        ->assertRedirect();

    expect($equipment->fresh()->token_hash)->toBeNull();

    $this->withToken('revocable-device-token')
        ->postJson(route('api.v1.heartbeats.store'), [])
        ->assertUnauthorized();
});

test('a paired app cannot revoke its own device credential', function () {
    $equipment = createPairingEquipment('PAIR-007');
    $token = 'app-cannot-revoke-token';
    $equipment->forceFill(['token_hash' => hash('sha256', $token)])->save();

    $this->withToken($token)
        ->deleteJson('/api/v1/device/pair')
        ->assertMethodNotAllowed();

    expect($equipment->fresh()->token_hash)->toBe(hash('sha256', $token));
});

test('a pairing code cannot authorize an app for the wrong device type', function () {
    $equipment = createPairingEquipment('PAIR-006');
    $equipment->forceFill([
        'tipo_equipo' => 'tablet',
        'codigo_vinculacion_hash' => hash('sha256', 'TABLETCODE123'),
        'codigo_vinculacion_expira_en' => now()->addMinutes(10),
    ])->save();

    $this->postJson(route('api.v1.device.pair'), [
        'codigo' => 'TABLETCODE123',
        'tipo' => 'cpu',
    ])->assertUnprocessable();

    expect($equipment->fresh()->codigo_vinculacion_hash)->toBe(hash('sha256', 'TABLETCODE123'));
});
