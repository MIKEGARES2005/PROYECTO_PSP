<?php

use App\Models\Docente;
use App\Models\Invitado;
use App\Models\User;

test('administrators can create and filter invitados', function () {
    $admin = createInvitadoAdminActor();

    $this->actingAs($admin)
        ->get(route('invitados.create'))
        ->assertOk()
        ->assertSee('name="ci_extension"', false)
        ->assertSee('value="LP"', false)
        ->assertSee('value="OR"', false)
        ->assertSee('value="PT"', false)
        ->assertSee('value="CB"', false)
        ->assertSee('value="SC"', false)
        ->assertSee('value="TJ"', false)
        ->assertSee('value="CH"', false)
        ->assertSee('value="BE"', false)
        ->assertSee('value="PD"', false)
        ->assertSee('value="5"', false)
        ->assertSee('5° semestre')
        ->assertSee('value="6"', false)
        ->assertSee('6° semestre');

    $response = $this->actingAs($admin)->post(route('invitados.store'), [
        'tipo' => 'externo',
        'nombres' => 'María',
        'apellido_paterno' => 'Vega',
        'apellido_materno' => 'Rojas',
        'ci' => '12345678',
        'ci_extension' => 'LP',
        'telefono' => '70000000',
        'procedencia' => 'Instituto invitado',
        'estado' => 'activo',
    ]);

    $response->assertRedirect(route('invitados.index'));
    $invitado = Invitado::where('ci', '12345678')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('invitados.index', ['buscar' => 'vega']))
        ->assertOk()
        ->assertSee($invitado->nombreCompleto());
});

test('administrators can register invitados in fifth and sixth semester', function (int $semestre) {
    $admin = createInvitadoAdminActor();

    $response = $this->actingAs($admin)->post(route('invitados.store'), [
        'tipo' => 'estudiante',
        'nombres' => 'María',
        'apellido_paterno' => 'Vega',
        'ci' => '12345678',
        'semestre' => $semestre,
        'estado' => 'activo',
    ]);

    $response->assertRedirect(route('invitados.index'));
    $this->assertDatabaseHas('invitados', [
        'ci' => '12345678',
        'semestre' => $semestre,
    ]);
})->with([5, 6]);

test('administrators cannot register an invitado above sixth semester', function () {
    $admin = createInvitadoAdminActor();

    $this->actingAs($admin)
        ->from(route('invitados.create'))
        ->post(route('invitados.store'), [
            'tipo' => 'estudiante',
            'nombres' => 'María',
            'apellido_paterno' => 'Vega',
            'ci' => '12345678',
            'semestre' => 7,
            'estado' => 'activo',
        ])
        ->assertRedirect(route('invitados.create'))
        ->assertSessionHasErrors('semestre');

    $this->assertDatabaseMissing('invitados', ['ci' => '12345678']);
});

test('administrators cannot create an invitado with invalid personal data', function () {
    $admin = createInvitadoAdminActor();

    $this->followingRedirects()
        ->actingAs($admin)
        ->from(route('invitados.create'))
        ->post(route('invitados.store'), [
            'tipo' => 'externo',
            'nombres' => 'María2',
            'apellido_paterno' => 'Vega',
            'apellido_materno' => 'Rojas',
            'ci' => '12A45678',
            'ci_extension' => 'LP',
            'telefono' => '7000000',
            'procedencia' => 'Instituto invitado',
            'estado' => 'activo',
        ])
        ->assertOk()
        ->assertSee('Por favor, introduzca los datos correctos.')
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo nombres solo puede contener letras y espacios.</p>', false)
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo carnet de identidad debe contener entre 5 y 15 dígitos, sin letras ni separadores.</p>', false)
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo teléfono debe contener exactamente 8 dígitos.</p>', false);

    $this->assertDatabaseMissing('invitados', ['ci' => '12A45678']);
});

test('administrators can view and edit an invitado', function () {
    $admin = createInvitadoAdminActor();
    $invitado = Invitado::create([
        'tipo' => 'estudiante',
        'nombres' => 'Luis',
        'apellido_paterno' => 'Prueba',
        'apellido_materno' => 'Invitado',
        'ci' => '87654321',
        'semestre' => 2,
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->get(route('invitados.show', $invitado))
        ->assertOk()
        ->assertSee('Luis Prueba Invitado');

    $this->actingAs($admin)
        ->put(route('invitados.update', $invitado), [
            'tipo' => 'docente',
            'nombres' => 'Luis Eduardo',
            'apellido_paterno' => 'Actualizado',
            'apellido_materno' => 'Invitado',
            'ci' => '87654321',
            'estado' => 'inactivo',
        ])
        ->assertRedirect(route('invitados.show', $invitado));

    $this->assertDatabaseHas('invitados', [
        'id' => $invitado->id,
        'nombres' => 'Luis Eduardo',
        'apellido_paterno' => 'Actualizado',
        'estado' => 'inactivo',
    ]);
});

test('administrators cannot update an invitado with invalid personal data', function () {
    $admin = createInvitadoAdminActor();
    $invitado = Invitado::create([
        'tipo' => 'externo',
        'nombres' => 'María',
        'apellido_paterno' => 'Vega',
        'apellido_materno' => 'Rojas',
        'ci' => '12345678',
        'estado' => 'activo',
    ]);

    $this->followingRedirects()
        ->actingAs($admin)
        ->from(route('invitados.edit', $invitado))
        ->put(route('invitados.update', $invitado), [
            'tipo' => 'externo',
            'nombres' => 'María2',
            'apellido_paterno' => 'Vega',
            'apellido_materno' => 'Rojas',
            'ci' => '12345678',
            'telefono' => '7000000',
            'estado' => 'activo',
        ])
        ->assertOk()
        ->assertSee('Por favor, introduzca los datos correctos.')
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo nombres solo puede contener letras y espacios.</p>', false)
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo teléfono debe contener exactamente 8 dígitos.</p>', false);

    expect($invitado->fresh()->nombres)->toBe('María')
        ->and($invitado->fresh()->telefono)->toBeNull();
});

test('administrators can archive an invitado', function () {
    $admin = createInvitadoAdminActor();
    $invitado = Invitado::create([
        'tipo' => 'externo',
        'nombres' => 'Invitado',
        'apellido_paterno' => 'Archivado',
        'ci' => '11223344',
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->delete(route('invitados.destroy', $invitado))
        ->assertRedirect(route('invitados.index'));

    $this->assertSoftDeleted('invitados', ['id' => $invitado->id]);
});

function createInvitadoAdminActor(): User
{
    $docente = Docente::create([
        'nombres' => 'Administrador',
        'apellido_paterno' => 'Prueba',
        'apellido_materno' => 'Sistema',
        'ci' => '99887766',
        'cargo' => 'Administrador',
        'estado' => 'activo',
    ]);

    return User::create([
        'username' => 'admin_invitados',
        'password' => 'password',
        'tipo_usuario' => 'administrador',
        'docente_id' => $docente->id,
    ]);
}
