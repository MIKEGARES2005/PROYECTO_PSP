<?php

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\User;
use App\Services\UserAnonymizationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('administrators can download a formal one-page user antecedent PDF with a profile photo', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $docente = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Plantilla',
        'apellido_materno' => 'Prueba',
        'ci' => '12345678',
        'ci_extension' => 'LP',
        'cargo' => 'Docente',
        'telefono' => '70000000',
        'correo_personal' => 'docente.plantilla@example.test',
        'especialidad' => 'Tecnología educativa',
        'nivel_academico' => 'Licenciatura',
        'fecha_ingreso' => '2021-02-01',
        'estado' => 'activo',
    ]);
    $docente->update([
        'foto_personal' => UploadedFile::fake()
            ->image('foto-perfil.png', 96, 96)
            ->store('fotos/docentes', 'public'),
    ]);
    $usuario = User::create([
        'username' => 'docente-plantilla',
        'password' => Hash::make('password-segura'),
        'tipo_usuario' => 'administrador',
        'docente_id' => $docente->id,
        'activo' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('usuarios.export', $usuario->id));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
    expect($response->headers->get('content-disposition'))
        ->toContain('Docente Plantilla Prueba.pdf');

    $pdf = Pdf::loadView('usuarios.pdf', compact('usuario'))->setPaper('letter', 'portrait');
    $pdf->render();
    $canvas = $pdf->getDomPDF()->getCanvas();

    expect($canvas->get_page_count())->toBe(1)
        ->and($canvas->get_width())->toBe(612.0)
        ->and($canvas->get_height())->toBe(792.0);
});

test('a complete student profile is presented on one formal letter-sized PDF page', function () {
    $student = Estudiante::create([
        'nombres' => 'María Fernanda',
        'apellido_paterno' => 'Quispe Mendoza',
        'apellido_materno' => 'Rojas Flores',
        'matricula' => 'MAT-2026-0042',
        'semestre' => 4,
        'generacion_ingreso' => 'I-2022',
        'ci' => '12345678',
        'ci_extension' => 'LP',
        'telefono' => '70000000',
        'correo_institucional' => 'maria.quispe@itibb.edu.bo',
        'correo_personal' => 'maria.quispe.personal@example.test',
        'direccion' => 'Zona Central, Av. Principal, #123, edificio institucional, departamento 4B',
        'estado' => 'activo',
    ]);
    $usuario = User::create([
        'username' => 'maria_quispe',
        'password' => Hash::make('password-segura'),
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
        'activo' => true,
    ]);

    $html = view('usuarios.pdf', compact('usuario'))->render();
    preg_match_all('/<table class="data-table">(.*?)<\/table>/s', $html, $tables);
    $dataTables = $tables[1] ?? [];
    $profileTable = $dataTables[0] ?? '';
    preg_match_all('/<td class="account-summary-cell(?: account-summary-(?:active|inactive))?">/', $html, $accountCards);
    preg_match_all('/<tr\b/', $profileTable, $profileRows);
    preg_match_all('/<td\b/', $profileTable, $profileCells);

    expect(count($dataTables))->toBe(1)
        ->and(count($accountCards[0]))->toBe(3)
        ->and(count($profileRows[0]))->toBe(6)
        ->and(count($profileCells[0]))->toBe(11)
        ->and($html)->toContain('maria_quispe')
        ->and($html)->toContain('Estudiante')
        ->and($html)->toContain('Activo')
        ->and($html)->toContain('font-size: 14px')
        ->and($html)->toContain('margin: 1cm 1.5cm;')
        ->and($html)->toContain('#272f7a')
        ->and($html)->toContain('background: #0b1226')
        ->and($html)->not->toContain('Fotografía personal')
        ->and($html)->not->toContain('Última actualización');

    $pdf = Pdf::loadView('usuarios.pdf', compact('usuario'))->setPaper('letter', 'portrait');
    $pdf->render();
    $canvas = $pdf->getDomPDF()->getCanvas();

    expect($canvas->get_page_count())->toBe(1)
        ->and($canvas->get_width())->toBe(612.0)
        ->and($canvas->get_height())->toBe(792.0);
});

test('administrators can create a docente profile and account together', function () {
    $admin = createUserAdminActor();

    $response = $this->actingAs($admin)->post(route('usuarios.store'), [
        'username' => 'docente-castañeta',
        'tipo_usuario' => 'docente',
        'password' => 'password-segura',
        'password_confirmation' => 'password-segura',
        'perfil' => [
            'nombres' => 'Ana',
            'apellido_paterno' => 'Prueba',
            'apellido_materno' => 'Docente',
            'ci' => '12345678',
            'ci_extension' => 'LP',
            'telefono' => '70000000',
            'cargo' => 'Docente',
            'nivel_academico' => 'Licenciatura',
            'fecha_ingreso' => '2026-01-10',
        ],
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', ['categoria' => 'docente']));

    $user = User::where('username', 'docente-castañeta')->firstOrFail();
    $this->assertDatabaseHas('docentes', [
        'id' => $user->docente_id,
        'nombres' => 'Ana',
        'apellido_paterno' => 'Prueba',
        'apellido_materno' => 'Docente',
        'ci' => '12345678',
        'ci_extension' => 'LP',
        'estado' => 'activo',
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Ana Prueba Docente',
        'username' => 'docente-castañeta',
        'tipo_usuario' => 'docente',
        'docente_id' => $user->docente_id,
        'estudiante_id' => null,
    ]);
    expect(Hash::check('password-segura', $user->password))->toBeTrue();
});

test('administrators cannot create a person with invalid names carnet or phone', function () {
    $admin = createUserAdminActor();
    $datos = [
        'username' => 'docente-datos-invalidos',
        'tipo_usuario' => 'docente',
        'password' => 'password-segura',
        'password_confirmation' => 'password-segura',
        'perfil' => [
            'nombres' => 'Ana2',
            'apellido_paterno' => 'Prueba',
            'apellido_materno' => 'Docente',
            'ci' => '12A45678',
            'ci_extension' => 'LP',
            'telefono' => '7000000',
            'cargo' => 'Docente',
        ],
    ];

    $this->followingRedirects()
        ->actingAs($admin)
        ->from(route('usuarios.create'))
        ->post(route('usuarios.store'), $datos)
        ->assertOk()
        ->assertSee('x-init="$store.toasts.push(', false)
        ->assertSee('Por favor, introduzca los datos correctos.')
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo nombres solo puede contener letras y espacios.</p>', false)
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo carnet de identidad debe contener entre 5 y 15 dígitos, sin letras ni separadores.</p>', false)
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo teléfono debe contener exactamente 8 dígitos.</p>', false)
        ->assertDontSee('resumen-validacion');

    $datos['perfil']['nombres'] = 'Ana';

    $this->followingRedirects()
        ->actingAs($admin)
        ->from(route('usuarios.create'))
        ->post(route('usuarios.store'), $datos)
        ->assertOk()
        ->assertSee('Por favor, introduzca los datos correctos.')
        ->assertDontSee('El campo nombres solo puede contener letras y espacios.')
        ->assertSee('El campo carnet de identidad debe contener entre 5 y 15 dígitos, sin letras ni separadores.')
        ->assertSee('El campo teléfono debe contener exactamente 8 dígitos.');

    $this->assertDatabaseMissing('users', ['username' => 'docente-datos-invalidos']);

    $datos['perfil']['ci'] = '12345678';
    $datos['perfil']['telefono'] = '70000000';

    $this->actingAs($admin)
        ->post(route('usuarios.store'), $datos)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', ['categoria' => 'docente']));

    $this->assertDatabaseHas('docentes', [
        'nombres' => 'Ana',
        'ci' => '12345678',
        'telefono' => '70000000',
    ]);
});

test('administrators can register carnet numbers at the allowed length boundaries', function (string $ci) {
    $admin = createUserAdminActor();

    $this->actingAs($admin)
        ->post(route('usuarios.store'), [
            'username' => 'docente-ci-'.strlen($ci),
            'tipo_usuario' => 'docente',
            'password' => 'password-segura',
            'password_confirmation' => 'password-segura',
            'perfil' => [
                'nombres' => 'María José',
                'apellido_paterno' => 'Ávila',
                'apellido_materno' => 'De la Cruz',
                'ci' => $ci,
                'ci_extension' => 'LP',
                'cargo' => 'Docente',
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', ['categoria' => 'docente']));

    $this->assertDatabaseHas('docentes', [
        'nombres' => 'María José',
        'apellido_paterno' => 'Ávila',
        'apellido_materno' => 'De la Cruz',
        'ci' => $ci,
    ]);
})->with([
    'minimum length' => '12345',
    'maximum length' => '123456789012345',
]);

test('administrator creation rejects apostrophes and hyphens in names', function (string $nombre) {
    $admin = createUserAdminActor();

    $this->actingAs($admin)
        ->from(route('usuarios.create'))
        ->post(route('usuarios.store'), [
            'username' => 'docente-nombre-invalido',
            'tipo_usuario' => 'docente',
            'password' => 'password-segura',
            'password_confirmation' => 'password-segura',
            'perfil' => [
                'nombres' => $nombre,
                'apellido_paterno' => 'Prueba',
                'apellido_materno' => 'Docente',
                'ci' => '12345678',
                'ci_extension' => 'LP',
                'cargo' => 'Docente',
            ],
        ])
        ->assertSessionHasErrors([
            'perfil.nombres' => 'El campo nombres solo puede contener letras y espacios.',
        ]);
})->with([
    'apostrophe' => "Ana María O'Connor",
    'hyphen' => 'Ana-María',
]);

test('administrators receive a password mismatch alert when creating an account', function () {
    $admin = createUserAdminActor();

    $response = $this->followingRedirects()
        ->actingAs($admin)
        ->from(route('usuarios.create'))
        ->post(route('usuarios.store'), [
            'username' => 'docente-password-distinta',
            'tipo_usuario' => 'docente',
            'password' => 'password-uno',
            'password_confirmation' => 'password-dos',
            'perfil' => [
                'nombres' => 'Ana',
                'apellido_paterno' => 'Prueba',
                'apellido_materno' => 'Docente',
                'ci' => '12345678',
                'ci_extension' => 'LP',
                'cargo' => 'Docente',
            ],
        ])
        ->assertOk()
        ->assertSee('x-init="$store.toasts.push(', false)
        ->assertSee('La confirmación de contraseña no coincide.');

    $this->assertDatabaseMissing('users', ['username' => 'docente-password-distinta']);
});

test('administrators see a password validation toast when the initial password is invalid', function () {
    $admin = createUserAdminActor();

    $response = $this->followingRedirects()
        ->actingAs($admin)
        ->from(route('usuarios.create'))
        ->post(route('usuarios.store'), [
            'username' => 'docente-password-corta',
            'tipo_usuario' => 'docente',
            'password' => 'corta',
            'password_confirmation' => 'corta',
            'perfil' => [
                'nombres' => 'Ana',
                'apellido_paterno' => 'Prueba',
                'apellido_materno' => 'Docente',
                'ci' => '12345678',
                'ci_extension' => 'LP',
                'cargo' => 'Docente',
            ],
        ])
        ->assertOk()
        ->assertSee('x-init="$store.toasts.push(', false)
        ->assertSee('Por favor, introduzca los datos correctos.')
        ->assertSee('El campo contraseña debe tener al menos 8 caracteres.');

    $this->assertDatabaseMissing('users', ['username' => 'docente-password-corta']);
});

test('administrators can create a second administrator with a new docente profile', function () {
    $admin = createUserAdminActor();

    $response = $this->actingAs($admin)->post(route('usuarios.store'), [
        'username' => 'admin-secundario',
        'tipo_usuario' => 'administrador',
        'password' => 'password-segura',
        'password_confirmation' => 'password-segura',
        'perfil' => [
            'nombres' => 'Mario',
            'apellido_paterno' => 'Admin',
            'apellido_materno' => 'Nuevo',
            'ci' => '12567890',
            'ci_extension' => 'SC',
            'cargo' => 'Administrador',
        ],
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', ['categoria' => 'administrador']));

    $user = User::where('username', 'admin-secundario')->firstOrFail();
    $this->assertDatabaseHas('docentes', [
        'id' => $user->docente_id,
        'nombres' => 'Mario',
        'cargo' => 'Administrador',
        'estado' => 'activo',
    ]);
    expect($user->tipo_usuario)->toBe('administrador');
});

test('administrators can create a student profile and account together', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();

    $response = $this->actingAs($admin)->post(route('usuarios.store'), [
        'username' => 'estudiante-prueba',
        'tipo_usuario' => 'estudiante',
        'password' => 'password-segura',
        'password_confirmation' => 'password-segura',
        'foto_personal' => UploadedFile::fake()->image('estudiante.jpg'),
        'perfil' => [
            'nombres' => 'Luis',
            'apellido_paterno' => 'Prueba',
            'apellido_materno' => 'Estudiante',
            'ci' => '87654321',
            'ci_extension' => 'CB',
            'telefono' => '71111111',
            'matricula' => 'MAT1001',
            'semestre' => 1,
            'generacion_ingreso' => 'I-2027',
            'correo_institucional' => 'luis@instituto.edu',
            'correo_personal' => 'luis@example.test',
        ],
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', ['categoria' => 'estudiante']));

    $user = User::where('username', 'estudiante-prueba')->firstOrFail();
    $this->assertDatabaseHas('estudiantes', [
        'id' => $user->estudiante_id,
        'nombres' => 'Luis',
        'matricula' => 'MAT1001',
        'semestre' => 1,
        'generacion_ingreso' => 'I-2027',
        'ci_extension' => 'CB',
        'estado' => 'activo',
    ]);
    $student = Estudiante::findOrFail($user->estudiante_id);
    expect($student->foto_personal)->toStartWith('fotos/estudiantes/');
    Storage::disk('public')->assertExists($student->foto_personal);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Luis Prueba Estudiante',
        'tipo_usuario' => 'estudiante',
        'docente_id' => null,
        'estudiante_id' => $user->estudiante_id,
    ]);
});

test('student accounts use numeric semesters in the creation form and request', function () {
    $admin = createUserAdminActor();

    $this->actingAs($admin)
        ->get(route('usuarios.create'))
        ->assertOk()
        ->assertSee('value="1"', false)
        ->assertSee('>Primero</option>', false)
        ->assertSee('Mostrar contraseña', false)
        ->assertSee('Confirmar contraseña')
        ->assertSee('Las contraseñas no coinciden. Verifica que ambas sean iguales.')
        ->assertSee('x-show="passwordMismatch()"', false)
        ->assertSee('x-effect="notifyPasswordMismatch()"', false)
        ->assertSee('value="4"', false)
        ->assertSee('>Cuarto</option>', false)
        ->assertSee('name="perfil[ci_extension]"', false)
        ->assertSee('value="LP"', false)
        ->assertSee('value="OR"', false)
        ->assertSee('value="PT"', false)
        ->assertSee('value="CB"', false)
        ->assertSee('value="SC"', false)
        ->assertSee('value="TJ"', false)
        ->assertSee('value="CH"', false)
        ->assertSee('value="BE"', false)
        ->assertSee('value="PD"', false)
        ->assertSee('Sugerir credenciales')
        ->assertSee('Lo sentimos, necesita llenar el formulario anterior para sugerir credenciales para este usuario')
        ->assertSee('name="direccion_zona"', false)
        ->assertSee('name="direccion_tipo_via"', false)
        ->assertSee('name="direccion_via"', false)
        ->assertSee('name="direccion_numero_puerta"', false)
        ->assertSee('lang="es"', false)
        ->assertSee('id="nombres" name="perfil[nombres]"', false)
        ->assertSee('data-capitalize-first', false)
        ->assertSee('autocapitalize="sentences" autocorrect="on" spellcheck="true"', false)
        ->assertSee('id="ci" name="perfil[ci]"', false)
        ->assertSee('id="correo_personal" name="perfil[correo_personal]"', false)
        ->assertSee('autocapitalize="none" autocorrect="off" spellcheck="false"', false)
        ->assertSee('data-email-domain="gmail.com"', false)
        ->assertSee('data-email-domain="itibb.edu.bo"', false)
        ->assertSee('@gmail.com')
        ->assertSee('@itibb.edu.bo')
        ->assertSee('autocomplete="address-level3"', false)
        ->assertSee('autocomplete="address-line1"', false)
        ->assertSee('autocomplete="address-line2" autocapitalize="none" autocorrect="off" spellcheck="false"', false);

    $response = $this->actingAs($admin)->post(route('usuarios.store'), [
        'username' => 'estudiante-cuarto',
        'tipo_usuario' => 'estudiante',
        'password' => 'password-segura',
        'password_confirmation' => 'password-segura',
        'perfil' => [
            'nombres' => 'Daniel',
            'apellido_paterno' => 'Cuarto',
            'apellido_materno' => 'Prueba',
            'ci' => '12345679',
            'ci_extension' => 'LP',
            'matricula' => 'MAT1002',
            'semestre' => 4,
            'generacion_ingreso' => 'II-2027',
        ],
        'direccion_zona' => 'Villa Esperanza',
        'direccion_tipo_via' => 'Av.',
        'direccion_via' => 'Bolívar',
        'direccion_numero_puerta' => '123',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', ['categoria' => 'estudiante']));

    $this->assertDatabaseHas('estudiantes', [
        'matricula' => 'MAT1002',
        'semestre' => 4,
        'generacion_ingreso' => 'II-2027',
        'direccion' => 'Zn. Villa Esperanza, Av. Bolívar, #123',
    ]);
});

test('administrators cannot create an account without the required personal profile data', function () {
    $admin = createUserAdminActor();
    $docentesAntes = Docente::count();

    $response = $this->actingAs($admin)->post(route('usuarios.store'), [
        'username' => 'admin-sin-perfil',
        'tipo_usuario' => 'administrador',
        'password' => 'password-segura',
        'password_confirmation' => 'password-segura',
    ]);

    $response->assertSessionHasErrors([
        'perfil.nombres',
        'perfil.apellido_paterno',
        'perfil.apellido_materno',
        'perfil.ci',
    ]);
    $this->assertDatabaseMissing('users', ['username' => 'admin-sin-perfil']);
    $this->assertDatabaseCount('docentes', $docentesAntes);
});

test('administrators can edit personal data without changing a user account', function () {
    $admin = createUserAdminActor();
    $targetTeacher = createUserTargetTeacher();
    $targetTeacher->update([
        'correo_personal' => 'docente-original@example.test',
        'especialidad' => 'Sistemas informáticos',
        'fecha_ingreso' => '2020-02-03',
    ]);
    $targetUser = createUserTeacherAccount($targetTeacher);
    $originalUsername = $targetUser->username;
    $originalPassword = $targetUser->password;

    $this->actingAs($admin)
        ->get(route('usuarios.personal.edit', $targetUser->id))
        ->assertSee('Editar datos personales')
        ->assertSee($targetUser->username)
        ->assertSee('Correo personal')
        ->assertSee('Especialidad')
        ->assertSee('value="2020-02-03"', false)
        ->assertDontSee('name="password"')
        ->assertDontSee('name="username"');

    $response = $this->actingAs($admin)->patch(
        route('usuarios.personal.update', $targetUser->id),
        [
            'nombres' => 'Nombre editado',
            'apellido_paterno' => 'Apellido nuevo',
            'apellido_materno' => 'Segundo apellido nuevo',
            'ci' => $targetTeacher->ci,
            'ci_extension' => 'OR',
            'telefono' => '72222222',
            'correo_personal' => 'docente-actualizado@example.test',
            'cargo' => 'Docente actualizado',
            'especialidad' => 'Redes y telecomunicaciones',
            'nivel_academico' => 'Maestría',
            'fecha_ingreso' => '2021-04-05',
            'username' => 'usuario-no-permitido',
            'name' => 'Nombre de cuenta no permitido',
            'password' => 'nueva-password',
            'tipo_usuario' => 'administrador',
            'docente_id' => $admin->docente_id,
            'estado' => 'inactivo',
        ]
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.show', $targetUser->id));

    $this->assertDatabaseHas('docentes', [
        'id' => $targetTeacher->id,
        'nombres' => 'Nombre editado',
        'apellido_paterno' => 'Apellido nuevo',
        'apellido_materno' => 'Segundo apellido nuevo',
        'ci_extension' => 'OR',
        'telefono' => '72222222',
        'correo_personal' => 'docente-actualizado@example.test',
        'cargo' => 'Docente actualizado',
        'especialidad' => 'Redes y telecomunicaciones',
        'nivel_academico' => 'Maestría',
        'fecha_ingreso' => '2021-04-05',
        'estado' => 'activo',
    ]);

    $targetUser->refresh();
    expect($targetUser->username)->toBe($originalUsername);
    expect($targetUser->password)->toBe($originalPassword);
    expect($targetUser->tipo_usuario)->toBe('docente');
    expect($targetUser->docente_id)->toBe($targetTeacher->id);
});

test('administrators cannot save invalid personal names carnet or phone', function () {
    $admin = createUserAdminActor();
    $targetTeacher = createUserTargetTeacher();
    $targetUser = createUserTeacherAccount($targetTeacher);

    $this->followingRedirects()
        ->actingAs($admin)
        ->from(route('usuarios.personal.edit', $targetUser->id))
        ->patch(route('usuarios.personal.update', $targetUser->id), [
            'nombres' => 'Ana2',
            'apellido_paterno' => $targetTeacher->apellido_paterno,
            'apellido_materno' => $targetTeacher->apellido_materno,
            'ci' => '12A45678',
            'ci_extension' => 'LP',
            'telefono' => '7000000',
            'cargo' => $targetTeacher->cargo,
        ])
        ->assertOk()
        ->assertSee('Por favor, introduzca los datos correctos.')
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo nombres solo puede contener letras y espacios.</p>', false)
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo carnet de identidad debe contener entre 5 y 15 dígitos, sin letras ni separadores.</p>', false)
        ->assertSee('<p class="mt-2 text-sm text-red-600">El campo teléfono debe contener exactamente 8 dígitos.</p>', false);

    expect($targetTeacher->fresh()->nombres)->toBe($targetTeacher->nombres)
        ->and($targetTeacher->fresh()->ci)->not->toBe('12A45678')
        ->and($targetTeacher->fresh()->telefono)->not->toBe('7000000');
});

test('editing another users profile shows that profiles photo instead of the administrators photo', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $adminPhotoPath = 'fotos/docentes/administrador.jpg';
    Storage::disk('public')->put($adminPhotoPath, 'admin-photo');
    $admin->docente->update(['foto_personal' => $adminPhotoPath]);

    $targetTeacher = createUserTargetTeacher();
    $targetPhotoPath = 'fotos/docentes/docente-objetivo.jpg';
    Storage::disk('public')->put($targetPhotoPath, 'target-photo');
    $targetTeacher->update(['foto_personal' => $targetPhotoPath]);
    $targetUser = createUserTeacherAccount($targetTeacher);

    $this->actingAs($admin)
        ->get(route('usuarios.personal.edit', $targetUser->id))
        ->assertSee('src="'.route('usuarios.photo', $targetUser->id).'"', false)
        ->assertDontSee(route('profile.photo'), false)
        ->assertDontSee(Storage::disk('public')->url($adminPhotoPath), false);

    $this->actingAs($admin)
        ->get(route('usuarios.photo', $targetUser->id))
        ->assertOk();
});

test('editing a profile without a photo shows the empty photo placeholder', function () {
    $admin = createUserAdminActor();
    $targetUser = createUserTeacherAccount(createUserTargetTeacher());

    $this->actingAs($admin)
        ->get(route('usuarios.personal.edit', $targetUser->id))
        ->assertSeeText('Sin foto')
        ->assertDontSee(route('profile.photo'), false);
});

test('administrators can edit student personal data without changing their account', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Original',
        'apellido_materno' => 'Prueba',
        'matricula' => 'MAT2001',
        'semestre' => 1,
        'generacion_ingreso' => 'I-2024',
        'ci' => '99001122',
        'telefono' => '73333333',
        'correo_institucional' => 'original@instituto.edu',
        'direccion' => 'Zn. Villa, C. Central, #42',
        'estado' => 'activo',
    ]);
    $studentUser = User::create([
        'name' => 'Estudiante Original Prueba',
        'username' => 'estudiante-original',
        'password' => 'password-segura',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);

    $this->actingAs($admin)
        ->get(route('usuarios.personal.edit', $studentUser->id))
        ->assertOk()
        ->assertSee('value="I-2024"', false)
        ->assertSee('value="Villa"', false)
        ->assertSee('value="Central"', false)
        ->assertSee('value="42"', false);

    $this->actingAs($admin)->patch(
        route('usuarios.personal.update', $studentUser->id),
        [
            'nombres' => 'Estudiante actualizado',
            'apellido_paterno' => 'Apellido actualizado',
            'apellido_materno' => 'Segundo actualizado',
            'matricula' => 'MAT2002',
            'semestre' => 2,
            'generacion_ingreso' => 'II-2025',
            'ci' => $student->ci,
            'ci_extension' => 'TJ',
            'telefono' => '74444444',
            'foto_personal' => UploadedFile::fake()->image('estudiante-actualizado.jpg'),
            'correo_institucional' => 'nuevo@instituto.edu',
            'correo_personal' => 'nuevo@example.test',
            'direccion_zona' => 'Villa Esperanza',
            'direccion_tipo_via' => 'C.',
            'direccion_via' => 'Sucre',
            'direccion_numero_puerta' => '456',
            'username' => 'no-cambiar-usuario',
            'tipo_usuario' => 'administrador',
            'estado' => 'inactivo',
        ]
    )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.show', $studentUser->id));

    $this->assertDatabaseHas('estudiantes', [
        'id' => $student->id,
        'nombres' => 'Estudiante actualizado',
        'apellido_paterno' => 'Apellido actualizado',
        'apellido_materno' => 'Segundo actualizado',
        'matricula' => 'MAT2002',
        'semestre' => 2,
        'generacion_ingreso' => 'II-2025',
        'ci_extension' => 'TJ',
        'telefono' => '74444444',
        'correo_institucional' => 'nuevo@instituto.edu',
        'direccion' => 'Zn. Villa Esperanza, C. Sucre, #456',
        'estado' => 'activo',
    ]);
    $updatedStudent = $student->fresh();
    expect($updatedStudent->foto_personal)->toStartWith('fotos/estudiantes/');
    Storage::disk('public')->assertExists($updatedStudent->foto_personal);

    $this->actingAs($admin)
        ->get(route('usuarios.show', $studentUser->id))
        ->assertSee(route('usuarios.photo', $studentUser->id), false);

    expect($studentUser->fresh()->username)->toBe('estudiante-original');
    expect($studentUser->fresh()->tipo_usuario)->toBe('estudiante');
});

test('administrators save structured address fields when editing a student', function () {
    $admin = createUserAdminActor();
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Direccion',
        'apellido_materno' => 'Prueba',
        'matricula' => 'MAT-DIRECCION',
        'semestre' => 1,
        'generacion_ingreso' => 'I-2026',
        'ci' => '99001126',
        'ci_extension' => 'LP',
        'direccion' => 'Zn. Villa, Av. Bolívar, #123',
        'estado' => 'activo',
    ]);
    $studentUser = User::create([
        'username' => 'estudiante-direccion',
        'password' => 'password-segura',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);

    $this->actingAs($admin)
        ->get(route('usuarios.personal.edit', $studentUser->id))
        ->assertSee('value="Villa"', false)
        ->assertSee('value="Bolívar"', false)
        ->assertSee('value="123"', false);

    $this->actingAs($admin)
        ->patch(route('usuarios.personal.update', $studentUser->id), [
            'nombres' => 'Estudiante',
            'apellido_paterno' => 'Direccion',
            'apellido_materno' => 'Prueba',
            'matricula' => 'MAT-DIRECCION',
            'semestre' => 1,
            'generacion_ingreso' => 'I-2026',
            'ci' => '99001126',
            'ci_extension' => 'LP',
            'direccion_zona' => 'Villa Esperanza',
            'direccion_tipo_via' => 'C.',
            'direccion_via' => 'Sucre',
            'direccion_numero_puerta' => '456',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.show', $studentUser->id));

    $this->assertDatabaseHas('estudiantes', [
        'id' => $student->id,
        'direccion' => 'Zn. Villa Esperanza, C. Sucre, #456',
    ]);
});

test('administrators can update student semesters with numeric values', function () {
    $admin = createUserAdminActor();
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Semestre',
        'apellido_materno' => 'Prueba',
        'matricula' => 'MAT2003',
        'semestre' => 3,
        'generacion_ingreso' => 'II-2026',
        'ci' => '99001125',
        'ci_extension' => 'LP',
        'estado' => 'activo',
    ]);
    $studentUser = User::create([
        'name' => 'Estudiante Semestre Prueba',
        'username' => 'estudiante-semestre',
        'password' => 'password-segura',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);

    $this->actingAs($admin)
        ->get(route('usuarios.personal.edit', $studentUser->id))
        ->assertOk()
        ->assertSee('value="3"', false)
        ->assertSee('value="4"', false)
        ->assertSee('>Cuarto</option>', false);

    $response = $this->actingAs($admin)->patch(
        route('usuarios.personal.update', $studentUser->id),
        [
            'nombres' => 'Estudiante',
            'apellido_paterno' => 'Semestre',
            'apellido_materno' => 'Prueba',
            'matricula' => 'MAT2003',
            'semestre' => 4,
            'generacion_ingreso' => 'II-2026',
            'ci' => '99001125',
            'ci_extension' => 'LP',
        ]
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.show', $studentUser->id));

    $this->assertDatabaseHas('estudiantes', [
        'id' => $student->id,
        'semestre' => 4,
    ]);
});

test('administrators can record the end of system tracking after all third-semester enrollments are approved', function () {
    $admin = createUserAdminActor();
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Tercero',
        'apellido_materno' => 'Prueba',
        'matricula' => 'MAT3001',
        'semestre' => 3,
        'generacion_ingreso' => 'I-2026',
        'ci' => '99001123',
        'estado' => 'activo',
    ]);
    $studentUser = User::create([
        'name' => 'Estudiante Tercero Prueba',
        'username' => 'estudiante-tercero',
        'password' => 'password-segura',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);
    $subject = Materia::create([
        'nombre' => 'Materia de Tercero',
        'semestre' => 3,
        'estado' => 'activo',
    ]);
    $enrollment = Inscripcion::create([
        'estudiante_id' => $student->id,
        'materia_id' => $subject->id,
        'gestion' => '2027-I',
        'tipo' => 'Normal',
        'estado' => 'Aprobado',
        'fecha_inscripcion' => '2027-02-01',
    ]);

    $this->actingAs($admin)
        ->patch(route('usuarios.seguimiento.finalizar', $studentUser->id), [
            'gestion_fin' => '2027-I',
        ])
        ->assertRedirect(route('usuarios.show', $studentUser->id))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('historial_seguimiento_estudiantes', [
        'estudiante_id' => $student->id,
        'gestion_fin' => '2027-I',
        'registrado_por' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->from(route('usuarios.show', $studentUser->id))
        ->patch(route('inscripciones.status', $enrollment), [
            'estado' => 'Inactivo',
        ])
        ->assertSessionHasErrors('estado');

    $this->actingAs($admin)
        ->get(route('usuarios.show', $studentUser->id))
        ->assertOk()
        ->assertSeeText('Generación de ingreso')
        ->assertSeeText('I-2026')
        ->assertDontSeeText('Historial de seguimiento en este sistema')
        ->assertDontSeeText('Gestión de finalización')
        ->assertDontSeeText('Registrado por');

    $nextSubject = Materia::create([
        'nombre' => 'Otra materia de Tercero',
        'semestre' => 3,
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->from(route('inscripciones.create'))
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $nextSubject->id,
            'gestion' => '2027-II',
            'tipo' => 'Arrastre',
            'fecha_inscripcion' => '2027-08-01',
        ])
        ->assertSessionHasErrors('estudiante_id');
});

test('administrators cannot end system tracking before third-semester enrollments are approved', function () {
    $admin = createUserAdminActor();
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Tercero',
        'apellido_materno' => 'Pendiente',
        'matricula' => 'MAT3002',
        'semestre' => 3,
        'generacion_ingreso' => 'II-2026',
        'ci' => '99001124',
        'estado' => 'activo',
    ]);
    $studentUser = User::create([
        'name' => 'Estudiante Tercero Pendiente',
        'username' => 'estudiante-tercero-pendiente',
        'password' => 'password-segura',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);
    $subject = Materia::create([
        'nombre' => 'Materia pendiente',
        'semestre' => 3,
        'estado' => 'activo',
    ]);
    Inscripcion::create([
        'estudiante_id' => $student->id,
        'materia_id' => $subject->id,
        'gestion' => '2027-I',
        'tipo' => 'Normal',
        'estado' => 'Cursando',
        'fecha_inscripcion' => '2027-02-01',
    ]);

    $this->actingAs($admin)
        ->from(route('usuarios.show', $studentUser->id))
        ->patch(route('usuarios.seguimiento.finalizar', $studentUser->id), [
            'gestion_fin' => '2027-I',
        ])
        ->assertSessionHasErrors([
            'gestion_fin' => 'Todas las inscripciones de Tercero de la gestión deben estar aprobadas.',
        ]);

    $this->assertDatabaseMissing('historial_seguimiento_estudiantes', [
        'estudiante_id' => $student->id,
    ]);
});

test('docentes cannot edit personal data from administrator user management', function () {
    $teacher = createUserTeacherAccount(createUserTargetTeacher());
    $otherTeacher = createUserTeacherAccount(createUserTargetTeacher());

    $this->actingAs($teacher)
        ->get(route('usuarios.personal.edit', $otherTeacher->id))
        ->assertForbidden();

    $this->assertDatabaseHas('docentes', [
        'id' => $otherTeacher->docente_id,
        'nombres' => 'Docente',
    ]);
});

test('administrators can activate or deactivate accounts through profile state', function () {
    $admin = createUserAdminActor();
    $targetTeacher = createUserTargetTeacher('activo');
    $targetUser = createUserTeacherAccount($targetTeacher);
    DB::table('sessions')->insert([
        [
            'id' => 'deactivated-teacher-session',
            'user_id' => $targetUser->id,
            'payload' => 'session-payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'admin-session-after-teacher-deactivation',
            'user_id' => $admin->id,
            'payload' => 'session-payload',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $response = $this->actingAs($admin)->patch(
        route('usuarios.status', $targetUser->id),
        ['estado' => 'inactivo']
    );

    $response->assertRedirect(route('usuarios.show', $targetUser->id));
    $this->assertDatabaseHas('docentes', [
        'id' => $targetTeacher->id,
        'estado' => 'inactivo',
    ]);
    $this->assertDatabaseMissing('sessions', ['id' => 'deactivated-teacher-session']);
    $this->assertDatabaseHas('sessions', [
        'id' => 'admin-session-after-teacher-deactivation',
        'user_id' => $admin->id,
    ]);
});

test('administrators revoke student sessions without closing other guards when deactivating an account', function () {
    $admin = createUserAdminActor();
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Desactivado',
        'apellido_materno' => 'Prueba',
        'matricula' => 'MAT-DESACTIVADO-01',
        'semestre' => 1,
        'generacion_ingreso' => 'I-2026',
        'ci' => '87654321',
        'ci_extension' => 'LP',
        'estado' => 'activo',
    ]);
    $targetUser = User::create([
        'username' => 'estudiante-desactivado',
        'password' => 'password',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);
    $studentGuardKey = Auth::guard('student')->getName();
    $webGuardKey = Auth::guard('web')->getName();
    DB::table('sessions')->insert([
        [
            'id' => 'deactivated-student-session',
            'user_id' => null,
            'payload' => base64_encode(json_encode([
                $studentGuardKey => $targetUser->id,
            ], JSON_THROW_ON_ERROR)),
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'deactivated-student-admin-session',
            'user_id' => null,
            'payload' => base64_encode(json_encode([
                $studentGuardKey => $targetUser->id,
                $webGuardKey => $admin->id,
            ], JSON_THROW_ON_ERROR)),
            'last_activity' => now()->timestamp,
        ],
    ]);

    $this->actingAs($admin)
        ->patch(route('usuarios.status', $targetUser->id), ['estado' => 'inactivo'])
        ->assertRedirect(route('usuarios.show', $targetUser->id));

    expect($student->fresh()->estado)->toBe('inactivo');
    $standalonePayload = DB::table('sessions')
        ->where('id', 'deactivated-student-session')
        ->value('payload');
    $sharedPayload = DB::table('sessions')
        ->where('id', 'deactivated-student-admin-session')
        ->value('payload');

    expect(json_decode(base64_decode($standalonePayload), true))
        ->not->toHaveKey($studentGuardKey);
    expect(json_decode(base64_decode($sharedPayload), true))
        ->not->toHaveKey($studentGuardKey);
    expect(json_decode(base64_decode($sharedPayload), true))
        ->toHaveKey($webGuardKey, $admin->id);
});

test('administrators can archive a user and its personal profile', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $targetTeacher = createUserTargetTeacher();
    $targetUser = createUserTeacherAccount($targetTeacher);
    DB::table('sessions')->insert([
        [
            'id' => 'archived-teacher-session',
            'user_id' => $targetUser->id,
            'payload' => 'session-payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'admin-session-after-archive',
            'user_id' => $admin->id,
            'payload' => 'session-payload',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $response = $this->actingAs($admin)->delete(route('usuarios.destroy', $targetUser->id));

    $response
        ->assertRedirect(route('usuarios.index', ['categoria' => 'docente']))
        ->assertSessionHas('success', 'Perfil eliminado correctamente');

    $reportPath = DB::table('reportes_generados')
        ->where('generado_por', $admin->id)
        ->whereJsonContains('parametros->tipo', 'eliminacion_usuario')
        ->value('ruta');

    expect($reportPath)->not->toBeNull();
    Storage::disk('public')->assertExists($reportPath);

    $this->assertSoftDeleted('users', ['id' => $targetUser->id]);
    $this->assertSoftDeleted('docentes', ['id' => $targetTeacher->id]);
    $this->assertDatabaseMissing('users', ['id' => $targetUser->id, 'deleted_at' => null]);
    $this->assertDatabaseMissing('docentes', ['id' => $targetTeacher->id, 'deleted_at' => null]);
    $this->assertDatabaseMissing('sessions', ['id' => 'archived-teacher-session']);
    $this->assertDatabaseHas('sessions', [
        'id' => 'admin-session-after-archive',
        'user_id' => $admin->id,
    ]);

    $this->post('/logout');

    $this->from(route('login'))
        ->post(route('login'), [
            'username' => $targetUser->username,
            'password' => 'password',
        ])
        ->assertSessionHasErrors(['username']);

    $this->assertGuest();
});

test('administrators cannot archive their own account', function () {
    $admin = createUserAdminActor();

    $this->actingAs($admin)
        ->delete(route('usuarios.destroy', $admin->id))
        ->assertForbidden();

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'deleted_at' => null,
    ]);
});

test('administrators can archive and restore an admin account without a personal profile', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $targetUser = User::create([
        'username' => 'admin-sin-perfil',
        'password' => 'password',
        'tipo_usuario' => 'administrador',
    ]);

    $this->actingAs($admin)
        ->delete(route('usuarios.destroy', $targetUser->id))
        ->assertRedirect(route('usuarios.index', ['categoria' => 'administrador']))
        ->assertSessionHas('success', 'Perfil eliminado correctamente');

    $this->assertSoftDeleted('users', ['id' => $targetUser->id]);
    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'docente_id' => null,
        'estudiante_id' => null,
    ]);

    $reportPath = DB::table('reportes_generados')
        ->where('generado_por', $admin->id)
        ->whereJsonContains('parametros->tipo', 'eliminacion_usuario')
        ->whereJsonContains('parametros->usuario_id', $targetUser->id)
        ->value('ruta');

    expect($reportPath)->not->toBeNull();
    Storage::disk('public')->assertExists($reportPath);

    $this->actingAs($admin)
        ->post(route('usuarios.restore', $targetUser->id))
        ->assertRedirect(route('usuarios.deleted'))
        ->assertSessionHas('success', 'Perfil recuperado correctamente');

    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'deleted_at' => null,
    ]);
});

test('administrators can view archived profiles', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $targetTeacher = createUserTargetTeacher();
    $targetUser = createUserTeacherAccount($targetTeacher);

    $this->actingAs($admin)->delete(route('usuarios.destroy', $targetUser->id));

    $reportPath = DB::table('reportes_generados')
        ->whereJsonContains('parametros->tipo', 'eliminacion_usuario')
        ->whereJsonContains('parametros->usuario_id', $targetUser->id)
        ->value('ruta');

    $this->assertNotNull($reportPath);
    $pdfUrl = route('usuarios.deleted.pdf', ['filename' => basename($reportPath)]);

    $this->actingAs($admin)
        ->get(route('usuarios.deleted'))
        ->assertOk()
        ->assertSee($targetUser->username)
        ->assertSee('Perfiles eliminados')
        ->assertSee('Ver archivos PDF')
        ->assertSee('storage/app/public/reportes/usuarios-eliminados')
        ->assertSee(basename($reportPath))
        ->assertSee($pdfUrl, false)
        ->assertSee('Restaurar perfil')
        ->assertSee(route('usuarios.restore', $targetUser->id), false)
        ->assertSee('Eliminar definitivamente')
        ->assertSee(route('usuarios.finalize', $targetUser->id), false);

    $this->actingAs($admin)
        ->get($pdfUrl)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('administrators can finalize an archived profile while retaining its PDF backup', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $targetTeacher = createUserTargetTeacher();
    $photoPath = 'fotos/docentes/perfil-archivado.jpg';
    Storage::disk('public')->put($photoPath, 'foto personal');
    $targetTeacher->update([
        'foto_personal' => $photoPath,
        'telefono' => '70000000',
        'correo_personal' => 'docente.privado@example.test',
    ]);
    $targetUser = createUserTeacherAccount($targetTeacher);
    $targetUser->preguntasSeguridad()->create([
        'pregunta' => 'Pregunta personal',
        'respuesta_hash' => 'hash',
    ]);
    DB::table('sessions')->insert([
        'id' => 'finalized-teacher-session',
        'user_id' => $targetUser->id,
        'payload' => 'session-payload',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->delete(route('usuarios.destroy', $targetUser->id))
        ->assertRedirect(route('usuarios.index', ['categoria' => 'docente']));

    $report = DB::table('reportes_generados')
        ->whereJsonContains('parametros->tipo', 'eliminacion_usuario')
        ->whereJsonContains('parametros->usuario_id', $targetUser->id)
        ->first();

    expect($report)->not->toBeNull();
    Storage::disk('public')->assertExists($report->ruta);

    $this->actingAs($admin)
        ->post(route('usuarios.finalize', $targetUser->id))
        ->assertRedirect(route('usuarios.deleted'))
        ->assertSessionHas('success', 'Los datos personales del perfil se anonimizaron; el PDF original se conserva indefinidamente junto con el historial.');

    $user = DB::table('users')->where('id', $targetUser->id)->first();

    expect($user->anonymized_at)->not->toBeNull();
    $this->assertSoftDeleted('users', ['id' => $targetUser->id]);
    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'username' => 'eliminado_'.$targetUser->id,
        'activo' => false,
    ]);
    $this->assertSoftDeleted('docentes', ['id' => $targetTeacher->id]);
    $this->assertDatabaseHas('docentes', [
        'id' => $targetTeacher->id,
        'nombres' => 'Usuario eliminado',
        'ci' => 'ELIM-'.$targetUser->id,
        'telefono' => null,
        'correo_personal' => null,
        'foto_personal' => null,
    ]);
    $this->assertDatabaseHas('reportes_generados', ['id' => $report->id]);
    $this->assertDatabaseMissing('preguntas_seguridad', ['user_id' => $targetUser->id]);
    $this->assertDatabaseMissing('sessions', ['id' => 'finalized-teacher-session']);
    $this->assertDatabaseHas('bitacora', [
        'accion' => 'eliminar_perfil_usuario',
        'entidad_id' => $targetUser->id,
    ]);
    $this->assertDatabaseHas('bitacora', [
        'accion' => 'anonimizar_perfil_usuario',
        'entidad_id' => $targetUser->id,
    ]);
    Storage::disk('public')->assertMissing($photoPath);
    Storage::disk('public')->assertExists($report->ruta);

    $this->actingAs($admin)
        ->post(route('usuarios.restore', $targetUser->id))
        ->assertRedirect(route('usuarios.deleted'))
        ->assertSessionHasErrors('usuario');

    $this->actingAs($admin)
        ->get(route('usuarios.deleted'))
        ->assertOk()
        ->assertDontSee('eliminado_'.$targetUser->id)
        ->assertSee(basename($report->ruta));

    $this->actingAs($admin)
        ->get(route('usuarios.deleted.pdf', ['filename' => basename($report->ruta)]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('only administrators can finalize archived profiles', function () {
    $targetTeacher = createUserTargetTeacher();
    $targetUser = createUserTeacherAccount($targetTeacher);
    $targetUser->delete();
    $targetTeacher->delete();

    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Sin permisos',
        'matricula' => 'MAT-FINALIZE-01',
        'semestre' => 1,
        'generacion_ingreso' => 'I-2026',
        'ci' => '87654321',
        'estado' => 'activo',
    ]);
    $studentUser = User::create([
        'username' => 'estudiante-sin-permisos',
        'password' => 'password',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);

    $this->post(route('usuarios.finalize', $targetUser->id))
        ->assertRedirect(route('login'));

    $this->actingAs(createUserTeacherAccount(createUserTargetTeacher()))
        ->post(route('usuarios.finalize', $targetUser->id))
        ->assertForbidden();

    $this->actingAs($studentUser)
        ->post(route('usuarios.finalize', $targetUser->id))
        ->assertForbidden();

    $this->assertSoftDeleted('users', ['id' => $targetUser->id]);
    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'anonymized_at' => null,
    ]);
});

test('unsafe personal file paths prevent profile finalization', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $targetTeacher = createUserTargetTeacher();
    $photoPath = 'fotos/docentes/perfil-no-finalizado.jpg';
    Storage::disk('public')->put($photoPath, 'foto personal');
    $targetTeacher->update(['foto_personal' => $photoPath]);
    $targetUser = createUserTeacherAccount($targetTeacher);

    $this->actingAs($admin)->delete(route('usuarios.destroy', $targetUser->id));

    $report = DB::table('reportes_generados')
        ->whereJsonContains('parametros->tipo', 'eliminacion_usuario')
        ->whereJsonContains('parametros->usuario_id', $targetUser->id)
        ->first();
    $unsafePhotoPath = '../outside.jpg';
    DB::table('docentes')
        ->where('id', $targetTeacher->id)
        ->update(['foto_personal' => $unsafePhotoPath]);

    expect(fn () => app(UserAnonymizationService::class)->anonymize($targetUser, $admin))
        ->toThrow(RuntimeException::class);

    $this->assertSoftDeleted('users', ['id' => $targetUser->id]);
    $this->assertDatabaseHas('docentes', [
        'id' => $targetTeacher->id,
        'nombres' => 'Docente',
        'foto_personal' => $unsafePhotoPath,
    ]);
    Storage::disk('public')->assertExists($photoPath);
    Storage::disk('public')->assertExists($report->ruta);
});

test('administrators can restore an archived docente profile and account', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $targetTeacher = createUserTargetTeacher();
    $targetUser = createUserTeacherAccount($targetTeacher);

    $this->actingAs($admin)
        ->delete(route('usuarios.destroy', $targetUser->id))
        ->assertRedirect(route('usuarios.index', ['categoria' => 'docente']));

    $this->actingAs($admin)
        ->post(route('usuarios.restore', $targetUser->id))
        ->assertRedirect(route('usuarios.deleted'))
        ->assertSessionHas('success', 'Perfil recuperado correctamente');

    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'deleted_at' => null,
        'remember_token' => null,
    ]);
    $this->assertDatabaseHas('docentes', [
        'id' => $targetTeacher->id,
        'deleted_at' => null,
    ]);

    $this->post('/logout');

    $this->post(route('login'), [
        'username' => $targetUser->username,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
});

test('administrators cannot restore a profile after the two-day grace period', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $targetTeacher = createUserTargetTeacher();
    $targetUser = createUserTeacherAccount($targetTeacher);

    $this->actingAs($admin)->delete(route('usuarios.destroy', $targetUser->id));
    DB::table('users')->where('id', $targetUser->id)->update(['deleted_at' => now()->subDays(3)]);
    DB::table('docentes')->where('id', $targetTeacher->id)->update(['deleted_at' => now()->subDays(3)]);

    $this->actingAs($admin)
        ->post(route('usuarios.restore', $targetUser->id))
        ->assertRedirect(route('usuarios.deleted'))
        ->assertSessionHasErrors('usuario');

    $this->assertSoftDeleted('users', ['id' => $targetUser->id]);
    $this->assertSoftDeleted('docentes', ['id' => $targetTeacher->id]);
    $this->actingAs($admin)
        ->get(route('usuarios.deleted'))
        ->assertOk()
        ->assertDontSee('Restaurar perfil')
        ->assertSee('Plazo de restauración vencido')
        ->assertSee('Eliminar definitivamente');
});

test('administrators can archive and restore student profile and account access', function () {
    Storage::fake('public');
    $admin = createUserAdminActor();
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Restaurado',
        'apellido_materno' => 'Prueba',
        'matricula' => 'MAT-RESTORE-01',
        'semestre' => 1,
        'generacion_ingreso' => 'I-2026',
        'ci' => '87654321',
        'ci_extension' => 'LP',
        'estado' => 'activo',
    ]);
    $studentUser = User::create([
        'username' => 'estudiante-restaurado',
        'password' => 'password',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);
    $studentGuardKey = Auth::guard('student')->getName();
    DB::table('sessions')->insert([
        'id' => 'archived-student-session',
        'user_id' => null,
        'payload' => base64_encode(json_encode([
            $studentGuardKey => $studentUser->id,
        ], JSON_THROW_ON_ERROR)),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->delete(route('usuarios.destroy', $studentUser->id))
        ->assertRedirect(route('usuarios.index', ['categoria' => 'estudiante']));

    $this->assertSoftDeleted('users', ['id' => $studentUser->id]);
    $this->assertSoftDeleted('estudiantes', ['id' => $student->id]);
    $archivedStudentPayload = DB::table('sessions')
        ->where('id', 'archived-student-session')
        ->value('payload');

    expect(json_decode(base64_decode($archivedStudentPayload), true))
        ->not->toHaveKey($studentGuardKey);

    $this->post('/logout');

    $this->from(route('estudiante.login'))
        ->post(route('estudiante.login.store'), [
            'username' => $studentUser->username,
            'password' => 'password',
        ])
        ->assertSessionHasErrors(['username']);
    $this->assertGuest('student');

    $this->actingAs($admin)
        ->post(route('usuarios.restore', $studentUser->id))
        ->assertRedirect(route('usuarios.deleted'))
        ->assertSessionHas('success', 'Perfil recuperado correctamente');

    $this->assertDatabaseHas('users', [
        'id' => $studentUser->id,
        'deleted_at' => null,
        'remember_token' => null,
    ]);
    $this->assertDatabaseHas('estudiantes', [
        'id' => $student->id,
        'deleted_at' => null,
    ]);

    $this->post('/logout');

    $this->post(route('estudiante.login.store'), [
        'username' => $studentUser->username,
        'password' => 'password',
    ])->assertRedirect(route('estudiante.dashboard', absolute: false));
});

test('administrators can recover docente accounts with temporary credentials', function () {
    $admin = createUserAdminActor();
    $targetUser = createUserTeacherAccount(createUserTargetTeacher());
    $securityQuestion = $targetUser->preguntasSeguridad()->create([
        'pregunta' => '¿Cuál es mi apodo de infancia?',
        'respuesta_hash' => Hash::make('respuesta privada'),
    ]);
    $targetUser->docente->update([
        'nombres' => 'Miguel Angel',
        'apellido_paterno' => 'Castañeta',
        'ci' => '15645072',
    ]);
    $targetUser->setRememberToken('remember-me-token');
    $targetUser->save();
    DB::table('sessions')->insert([
        [
            'id' => 'docente-session-1',
            'user_id' => $targetUser->id,
            'payload' => 'session-payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'docente-session-2',
            'user_id' => $targetUser->id,
            'payload' => 'session-payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'admin-session',
            'user_id' => $admin->id,
            'payload' => 'session-payload',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $response = $this->actingAs($admin)->postJson(
        route('usuarios.recover', $targetUser->id),
        ['admin_password' => 'password']
    );

    $response->assertOk()->assertJsonPath('success', true);

    $username = $response->json('username');
    $password = $response->json('password');
    $updatedUser = $targetUser->fresh();

    expect($username)->toBe('miguelangel_castañeta');
    expect($password)->toBe('castañeta15645072');
    expect($updatedUser->username)->toBe($username);
    expect($updatedUser->debe_cambiar_password)->toBeTrue();
    $this->assertModelExists($securityQuestion);
    expect($securityQuestion->fresh()->pregunta)->toBe('¿Cuál es mi apodo de infancia?');
    expect($securityQuestion->fresh()->respuesta_hash)->toBe($securityQuestion->respuesta_hash);
    $this->assertDatabaseHas('users', [
        'id' => $updatedUser->id,
        'remember_token' => null,
    ]);
    $this->assertDatabaseHas('notificaciones', [
        'user_id' => $updatedUser->id,
        'tipo' => 'recuperacion_aprobada',
        'titulo' => 'Recuperación aprobada',
        'url' => route('profile.edit'),
    ]);
    expect(Hash::check($password, $updatedUser->password))->toBeTrue();
    $this->assertDatabaseMissing('sessions', ['id' => 'docente-session-1']);
    $this->assertDatabaseMissing('sessions', ['id' => 'docente-session-2']);
    $this->assertDatabaseHas('sessions', ['id' => 'admin-session', 'user_id' => $admin->id]);

    $this->post('/logout');

    $this->post('/login', [
        'username' => $username,
        'password' => $password,
    ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('teacher_welcome', true)
        ->assertSessionHas('teacher_recovery_welcome', true);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="bienvenida-docente"', false)
        ->assertSee('Tus preguntas de seguridad se conservan')
        ->assertDontSee('registra tus preguntas de seguridad desde el menú')
        ->assertSee('Entendido, ir al panel de inicio');
});

test('administrators cannot recover docente accounts with an incorrect password', function () {
    $admin = createUserAdminActor();
    $targetUser = createUserTeacherAccount(createUserTargetTeacher());
    $originalUsername = $targetUser->username;
    $originalPassword = $targetUser->password;
    DB::table('sessions')->insert([
        'id' => 'docente-session-invalid-password',
        'user_id' => $targetUser->id,
        'payload' => 'session-payload',
        'last_activity' => now()->timestamp,
    ]);

    $response = $this->actingAs($admin)->postJson(
        route('usuarios.recover', $targetUser->id),
        ['admin_password' => 'incorrecta']
    );

    $response->assertUnprocessable()->assertJsonPath('success', false);
    expect($targetUser->fresh()->username)->toBe($originalUsername);
    expect($targetUser->fresh()->password)->toBe($originalPassword);
    $this->assertDatabaseHas('sessions', ['id' => 'docente-session-invalid-password']);
    $this->assertDatabaseMissing('notificaciones', [
        'user_id' => $targetUser->id,
        'tipo' => 'recuperacion_aprobada',
    ]);
});

test('administrators can recover estudiante accounts with temporary credentials', function () {
    $admin = createUserAdminActor();
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Castañeta',
        'apellido_materno' => 'Sistema',
        'matricula' => fake()->unique()->numerify('MAT#####'),
        'semestre' => 1,
        'generacion_ingreso' => 'I-2026',
        'ci' => fake()->unique()->numerify('########'),
        'ci_extension' => 'LP',
        'estado' => 'activo',
    ]);
    $targetUser = User::create([
        'name' => 'Estudiante',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);
    $securityQuestion = $targetUser->preguntasSeguridad()->create([
        'pregunta' => '¿Cuál es mi libro favorito?',
        'respuesta_hash' => Hash::make('respuesta privada estudiante'),
    ]);
    $studentGuardKey = Auth::guard('student')->getName();
    $webGuardKey = Auth::guard('web')->getName();
    DB::table('sessions')->insert([
        [
            'id' => 'estudiante-session',
            'user_id' => null,
            'payload' => base64_encode(json_encode([
                $studentGuardKey => $targetUser->id,
            ], JSON_THROW_ON_ERROR)),
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'estudiante-admin-session',
            'user_id' => $admin->id,
            'payload' => base64_encode(json_encode([
                $studentGuardKey => $targetUser->id,
                $webGuardKey => $admin->id,
            ], JSON_THROW_ON_ERROR)),
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'admin-session',
            'user_id' => $admin->id,
            'payload' => 'session-payload',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $response = $this->actingAs($admin)->postJson(
        route('usuarios.recover', $targetUser->id),
        ['admin_password' => 'password']
    );

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('username'))->toBe('estudiante_castañeta');
    expect($response->json('password'))
        ->toBe('castañeta'.$student->ci);
    expect($targetUser->fresh()->debe_cambiar_password)->toBeTrue();
    $this->assertModelExists($securityQuestion);
    expect($securityQuestion->fresh()->pregunta)->toBe('¿Cuál es mi libro favorito?');
    expect($securityQuestion->fresh()->respuesta_hash)->toBe($securityQuestion->respuesta_hash);
    $this->assertDatabaseHas('notificaciones', [
        'user_id' => $targetUser->id,
        'tipo' => 'recuperacion_aprobada',
        'titulo' => 'Recuperación aprobada',
        'url' => route('estudiante.profile.edit'),
    ]);
    $studentSessionPayload = DB::table('sessions')
        ->where('id', 'estudiante-session')
        ->value('payload');
    $studentAdminSessionPayload = DB::table('sessions')
        ->where('id', 'estudiante-admin-session')
        ->value('payload');

    expect(json_decode(base64_decode($studentSessionPayload), true))->not->toHaveKey($studentGuardKey)
        ->and(json_decode(base64_decode($studentAdminSessionPayload), true))
        ->toHaveKey($webGuardKey, $admin->id);
    $this->assertDatabaseHas('sessions', ['id' => 'admin-session', 'user_id' => $admin->id]);

    $this->post('/logout');

    $this->post(route('estudiante.login.store'), [
        'username' => $response->json('username'),
        'password' => $response->json('password'),
    ])
        ->assertRedirect(route('estudiante.dashboard', absolute: false))
        ->assertSessionHas('student_welcome', true)
        ->assertSessionHas('student_recovery_welcome', true);

    $this->get(route('estudiante.dashboard'))
        ->assertOk()
        ->assertSee('id="bienvenida-estudiante"', false)
        ->assertSee('Tu cuenta fue recuperada.')
        ->assertSee('Tus preguntas de seguridad se conservan')
        ->assertDontSee('Tu solicitud fue aprobada');
});

test('administrators cannot deactivate their own account', function () {
    $admin = createUserAdminActor();

    $response = $this->actingAs($admin)->patch(
        route('usuarios.status', $admin->id),
        ['estado' => 'inactivo']
    );

    $response
        ->assertRedirect()
        ->assertSessionHas('error', 'No puedes desactivar tu propia cuenta.');

    $this->assertDatabaseHas('docentes', [
        'id' => $admin->docente_id,
        'estado' => 'activo',
    ]);
});

test('administrators can activate or deactivate a second administrator account', function () {
    $admin = createUserAdminActor();
    $secondAdminProfile = createUserTargetTeacher('Activa');
    $secondAdmin = User::create([
        'name' => 'Segundo administrador',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'administrador',
        'docente_id' => $secondAdminProfile->id,
    ]);

    $this->actingAs($admin)
        ->get(route('usuarios.show', $secondAdmin->id))
        ->assertDontSee(route('usuarios.personal.edit', $secondAdmin->id))
        ->assertSee('Desactivar cuenta');

    $this->actingAs($admin)
        ->patch(route('usuarios.status', $secondAdmin->id), ['estado' => 'inactivo'])
        ->assertRedirect(route('usuarios.show', $secondAdmin->id));

    $this->assertDatabaseHas('docentes', [
        'id' => $secondAdminProfile->id,
        'estado' => 'inactivo',
    ]);

    $this->actingAs($admin)
        ->patch(route('usuarios.status', $secondAdmin->id), ['estado' => 'activo'])
        ->assertRedirect(route('usuarios.show', $secondAdmin->id));

    $this->assertDatabaseHas('docentes', [
        'id' => $secondAdminProfile->id,
        'estado' => 'activo',
    ]);
});

test('administrators cannot edit account data through the user routes', function () {
    $admin = createUserAdminActor();
    $targetUser = createUserTeacherAccount(createUserTargetTeacher());

    $this->actingAs($admin)
        ->get('/usuarios/'.$targetUser->id.'/editar')
        ->assertNotFound();

    $this->actingAs($admin)
        ->put('/usuarios/'.$targetUser->id, [
            'name' => 'Nombre no permitido',
        ])
        ->assertStatus(405);

    $this->actingAs($admin)
        ->delete('/usuarios/'.$targetUser->id)
        ->assertStatus(405);

    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'name' => $targetUser->name,
    ]);
});

test('administrators can browse and search people by category', function () {
    $admin = createUserAdminActor();
    $teacher = createUserTargetTeacher();
    $teacherUser = createUserTeacherAccount($teacher);

    $this->actingAs($admin)
        ->get(route('usuarios.index', ['categoria' => 'docente']))
        ->assertOk()
        ->assertSee('Personas y usuarios')
        ->assertSee('Docentes')
        ->assertSee($teacherUser->username)
        ->assertSee('Docente Prueba Sistema');

    $this->actingAs($admin)
        ->get(route('usuarios.index', [
            'categoria' => 'administrador',
            'buscar' => $admin->username,
        ]))
        ->assertOk()
        ->assertSee($admin->username);
});

test('administrators can search students by names surnames username or matricula without case sensitivity', function () {
    $admin = createUserAdminActor();
    $student = Estudiante::create([
        'nombres' => 'Cris',
        'apellido_paterno' => 'Mendoza',
        'apellido_materno' => 'Vega',
        'matricula' => 'VRFT1234',
        'semestre' => 1,
        'generacion_ingreso' => 'I-2026',
        'ci' => fake()->unique()->numerify('########'),
        'ci_extension' => 'LP',
        'estado' => 'activo',
    ]);
    $studentUser = User::create([
        'name' => 'Cris Mendoza Vega',
        'username' => 'cris_mendo',
        'password' => 'password',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $student->id,
    ]);

    foreach (['cris', 'MENDOZA', 'vega', 'CRIS_MENDO', 'vrft1234'] as $term) {
        $this->actingAs($admin)
            ->get(route('usuarios.index', ['categoria' => 'estudiante', 'buscar' => $term]))
            ->assertOk()
            ->assertSee($studentUser->username);
    }
});

test('administrators can search students across all semesters', function () {
    $admin = createUserAdminActor();
    $firstStudent = Estudiante::create([
        'nombres' => 'Vega Primero',
        'apellido_paterno' => 'Rojas',
        'apellido_materno' => 'Quispe',
        'matricula' => 'VRFT2001',
        'semestre' => 1,
        'generacion_ingreso' => 'I-2026',
        'ci' => fake()->unique()->numerify('########'),
        'ci_extension' => 'LP',
        'estado' => 'activo',
    ]);
    $secondStudent = Estudiante::create([
        'nombres' => 'Vega Cuarto',
        'apellido_paterno' => 'Rojas',
        'apellido_materno' => 'Mamani',
        'matricula' => 'VRFT2004',
        'semestre' => 4,
        'generacion_ingreso' => 'II-2026',
        'ci' => fake()->unique()->numerify('########'),
        'ci_extension' => 'LP',
        'estado' => 'activo',
    ]);
    $firstUser = User::create([
        'name' => 'Vega Primero Rojas Quispe',
        'username' => 'vega_primero',
        'password' => 'password',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $firstStudent->id,
    ]);
    $secondUser = User::create([
        'name' => 'Vega Cuarto Rojas Mamani',
        'username' => 'vega_cuarto',
        'password' => 'password',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $secondStudent->id,
    ]);

    $this->actingAs($admin)
        ->get(route('usuarios.index', [
            'categoria' => 'estudiante',
            'semestre' => 'todos',
            'buscar' => 'ROJAS',
        ]))
        ->assertOk()
        ->assertSeeText('Estudiantes de todos los semestres')
        ->assertSee($firstUser->username)
        ->assertSee($secondUser->username);
});

test('administrators see students alphabetically by paternal surname for each semester filter', function (string|int $semestre, array $apellidosEsperados) {
    $admin = createUserAdminActor();
    $estudiantes = [
        ['nombre' => 'Zoe', 'apellido_paterno' => 'Zamora', 'semestre' => 4],
        ['nombre' => 'Ana', 'apellido_paterno' => 'Alvarez', 'semestre' => 1],
        ['nombre' => 'Mia', 'apellido_paterno' => 'Mamani', 'semestre' => 1],
    ];

    foreach ($estudiantes as $indice => $datosEstudiante) {
        $estudiante = Estudiante::create([
            'nombres' => $datosEstudiante['nombre'],
            'apellido_paterno' => $datosEstudiante['apellido_paterno'],
            'apellido_materno' => 'Apellido',
            'matricula' => 'ORDEN-'.($indice + 1),
            'semestre' => $datosEstudiante['semestre'],
            'generacion_ingreso' => 'I-2026',
            'ci' => 'ORDENCI'.($indice + 1),
            'ci_extension' => 'LP',
            'estado' => 'activo',
        ]);

        User::create([
            'username' => 'orden-'.$indice,
            'password' => 'password',
            'tipo_usuario' => 'estudiante',
            'estudiante_id' => $estudiante->id,
        ]);
    }

    $this->actingAs($admin)
        ->get(route('usuarios.index', [
            'categoria' => 'estudiante',
            'semestre' => $semestre,
        ]))
        ->assertViewHas('usuarios', function ($usuarios) use ($apellidosEsperados): bool {
            return $usuarios->getCollection()->pluck('estudiante.apellido_paterno')->all() === $apellidosEsperados;
        });
})->with([
    'all semesters' => ['todos', ['Alvarez', 'Mamani', 'Zamora']],
    'one semester' => [1, ['Alvarez', 'Mamani']],
]);

function createUserAdminActor(): User
{
    $docente = createUserTargetTeacher();

    return User::create([
        'name' => 'Administrador',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'administrador',
        'docente_id' => $docente->id,
    ]);
}

function createUserTargetTeacher(string $estado = 'activo'): Docente
{
    return Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Prueba',
        'apellido_materno' => 'Sistema',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => $estado,
    ]);
}

function createUserTeacherAccount(Docente $docente): User
{
    return User::create([
        'name' => 'Docente',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'docente',
        'docente_id' => $docente->id,
    ]);
}
