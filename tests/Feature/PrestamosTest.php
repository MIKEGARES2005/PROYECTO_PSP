<?php

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Invitado;
use App\Models\User;

test('administrators can register a loan for an active guest with an available individual equipment', function () {
    $admin = User::create([
        'username' => 'admin-prestamos',
        'password' => 'password',
        'tipo_usuario' => 'administrador',
    ]);
    $category = Categoria::create(['nombre' => 'Equipos de préstamo', 'grupo' => 'equipo', 'estado' => 'activa']);
    $asset = Activo::create([
        'codigo' => 'PRE-EQ-001',
        'categoria_id' => $category->id,
        'nombre' => 'Laptop para invitado',
        'estado_uso' => 'disponible',
    ]);
    $equipment = Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'PRE-LAB-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    $guest = Invitado::create([
        'tipo' => 'externo',
        'nombres' => 'Invitado',
        'apellido_paterno' => 'Prueba',
        'ci' => '90000001',
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->post(route('prestamos.store'), [
            'equipo_id' => $equipment->id,
            'invitado_id' => $guest->id,
            'fecha_entrega' => '2026-10-30 09:00:00',
            'fecha_devolucion_prevista' => '2026-10-30 17:00:00',
            'estado_fisico_entrega' => 'bueno',
        ])
        ->assertRedirect(route('asignaciones.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('asignaciones', [
        'tipo' => 'prestado',
        'equipo_id' => $equipment->id,
        'invitado_id' => $guest->id,
        'estado' => 'activa',
    ]);
    $this->assertDatabaseHas('activos', ['id' => $asset->id, 'estado_uso' => 'asignado']);
});

test('individual equipment cannot receive a second active loan', function () {
    $admin = User::create([
        'username' => 'admin-prestamos-2',
        'password' => 'password',
        'tipo_usuario' => 'administrador',
    ]);
    $category = Categoria::create(['nombre' => 'Equipos de préstamo 2', 'grupo' => 'equipo', 'estado' => 'activa']);
    $asset = Activo::create(['codigo' => 'PRE-EQ-002', 'categoria_id' => $category->id, 'nombre' => 'Equipo ocupado', 'estado_uso' => 'asignado']);
    $equipment = Equipo::create(['activo_id' => $asset->id, 'identificador' => 'PRE-LAB-002', 'tipo_equipo' => 'cpu', 'modo_uso' => 'individual']);
    $guest = Invitado::create(['tipo' => 'externo', 'nombres' => 'Invitado', 'apellido_paterno' => 'Ocupado', 'ci' => '90000002', 'estado' => 'activo']);

    $response = $this->actingAs($admin)->post(route('prestamos.store'), [
        'equipo_id' => $equipment->id,
        'invitado_id' => $guest->id,
        'fecha_entrega' => '2026-10-30 09:00:00',
        'fecha_devolucion_prevista' => '2026-10-30 17:00:00',
        'estado_fisico_entrega' => 'bueno',
    ]);

    $response->assertSessionHasErrors('equipo_id');
    expect(Asignacion::count())->toBe(0);
});
