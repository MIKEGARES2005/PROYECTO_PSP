<?php

use App\Models\Accesorio;
use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Complemento;
use App\Models\ConfiguracionAcademica;
use App\Models\Diagnostico;
use App\Models\Docente;
use App\Models\Equipo;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Heartbeat;
use App\Models\Horario;
use App\Models\Incidencia;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

beforeEach(function (): void {
    $this->withoutVite();
});

test('teachers see only current semesters and subjects assigned to them', function (): void {
    $teacher = teacherPanelUser();
    $otherTeacher = teacherPanelUser('Other');
    $gestion = Gestion::query()->where('codigo', 'II-2026')->firstOrFail();
    $gestion->update(['activa' => true]);
    ConfiguracionAcademica::query()->findOrFail(1)->update(['gestion_vigente' => $gestion->codigo]);
    $subject = teacherPanelSubject('Automation');
    $secondSemesterSubject = teacherPanelSubject('Robotics');
    $secondSemesterSubject->update(['semestre' => 'Segundo']);
    $otherSubject = teacherPanelSubject('Networks');
    teacherPanelSchedule($teacher, $subject)->update([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
    ]);
    teacherPanelSchedule($teacher, $secondSemesterSubject)->update([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'hora_inicio' => '10:00',
        'hora_fin' => '12:00',
    ]);
    teacherPanelSchedule($otherTeacher, $otherSubject)->update([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
    ]);
    $otherSubject->update(['semestre' => 'Primero']);
    $historicSubject = teacherPanelSubject('Historic subject');
    $historicSubject->update(['semestre' => 'Tercero']);
    teacherPanelSchedule($teacher, $historicSubject)->update(['gestion' => 'I-2026']);

    $this->actingAs($teacher)
        ->get(route('panel-docente.estudiantes'))
        ->assertOk()
        ->assertSeeText('Automation')
        ->assertSeeText('Robotics')
        ->assertSeeText('Primero semestre')
        ->assertSeeText('Segundo semestre')
        ->assertSeeText('Gestión Académica')
        ->assertSee(route('panel-docente.gestion-academica.semestre', [
            'semestre' => 'Primero',
            'gestion' => 'II-2026',
        ]), false)
        ->assertDontSeeText('Networks')
        ->assertDontSeeText('Tercero semestre')
        ->assertDontSeeText('Historic subject')
        ->assertDontSeeText('Buscar estudiante o materia');

    $this->actingAs($teacher)
        ->get(route('panel-docente.gestion-academica.semestre', [
            'semestre' => 'Primero',
            'gestion' => 'II-2026',
        ]))
        ->assertOk()
        ->assertSeeText('Automation')
        ->assertDontSeeText('Networks');
});

test('the academic panel shows docente and administrative profiles without account details', function (): void {
    $teacher = teacherPanelUser('Current');
    $colleague = teacherPanelProfile('Colleague');
    $administrativeProfile = teacherPanelProfile('Administrative');
    $colleagueUser = User::create([
        'name' => 'Private colleague account name',
        'username' => 'private-colleague-login',
        'password' => 'private-colleague-password',
        'tipo_usuario' => 'docente',
        'docente_id' => $colleague->id,
    ]);
    $administrativeUser = User::create([
        'name' => 'Administrative account',
        'username' => 'administrative-private-login',
        'password' => 'administrative-private-password',
        'tipo_usuario' => 'administrador',
        'docente_id' => $administrativeProfile->id,
    ]);
    $colleague->update([
        'telefono' => '70012345',
        'cargo' => 'Docente de laboratorio',
        'nivel_academico' => 'Licenciatura',
        'fecha_ingreso' => '2020-02-03',
    ]);

    $this->actingAs($teacher)
        ->get(route('panel-docente.academico'))
        ->assertOk()
        ->assertSeeText('Colleague')
        ->assertSeeText($colleague->ci)
        ->assertSeeText('70012345')
        ->assertSeeText('Docente de laboratorio')
        ->assertSeeText('Licenciatura')
        ->assertSeeText('03/02/2020')
        ->assertSeeText('Administrative')
        ->assertDontSeeText($colleagueUser->username)
        ->assertDontSeeText($administrativeUser->username)
        ->assertDontSeeText('administrative-private-password')
        ->assertDontSeeText('Esta sección muestra datos del perfil personal docente')
        ->assertDontSeeText('Volver al inicio')
        ->assertDontSeeText('private-colleague-password');
});

test('administrator and student accounts cannot access teacher-only panels', function (string $role): void {
    $attributes = [
        'name' => "Restricted {$role}",
        'username' => "restricted-{$role}",
        'password' => 'password',
        'tipo_usuario' => $role,
    ];

    if ($role === 'estudiante') {
        $attributes['estudiante_id'] = teacherPanelStudent('Restricted Student')->id;
    }

    $user = User::create($attributes);

    $this->actingAs($user)
        ->get(route('panel-docente.estudiantes'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('panel-docente.academico'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('panel-docente.seguimiento'))
        ->assertForbidden();
})->with([
    'administrator' => 'administrador',
    'student' => 'estudiante',
]);

test('guests are redirected from teacher-only panels', function (): void {
    $this->get(route('panel-docente.estudiantes'))
        ->assertRedirect(route('login'));

    $this->get(route('panel-docente.academico'))
        ->assertRedirect(route('login'));

    $this->get(route('panel-docente.seguimiento'))
        ->assertRedirect(route('login'));
});

test('the teacher dashboard renders its modules without a users section', function (): void {
    $teacher = teacherPanelUser();

    $this->withoutMiddleware(EnsureEmailIsVerified::class)
        ->actingAs($teacher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Panel docente')
        ->assertSeeText('Gestión Académica')
        ->assertSeeText('Panel Académico')
        ->assertSeeText('Seguimiento estudiantil')
        ->assertSeeText('Inventario')
        ->assertSeeText('Gestión vigente')
        ->assertDontSeeText('Panel Estudiantil')
        ->assertDontSee(route('gestion-academica.index'), false)
        ->assertSeeText('Monitoreo de mis equipos')
        ->assertSeeText('Diagnósticos críticos')
        ->assertSeeText('Mi cuenta')
        ->assertSeeText('Preguntas de seguridad')
        ->assertSeeText('Asignaciones')
        ->assertDontSeeText('Préstamos y asignaciones')
        ->assertSee(route('profile.edit'), false)
        ->assertSee(route('security.questions.edit'), false)
        ->assertSee('aria-label="Notificaciones"', false)
        ->assertSee('aria-label="Abrir acceso estudiantil QR"', false)
        ->assertSee(route('estudiante.qr', ['embed' => 1]), false)
        ->assertSee('via-fuchsia-600', false)
        ->assertDontSeeText('Panel de recursos')
        ->assertDontSeeText('Recursos docentes')
        ->assertDontSeeText('Personas y usuarios');

    $this->actingAs($teacher)
        ->get(route('usuarios.index'))
        ->assertForbidden();
});

test('the teacher dashboard leaves inventory in its dedicated section', function (): void {
    $teacher = teacherPanelUser();
    $this->withoutMiddleware(EnsureEmailIsVerified::class)
        ->actingAs($teacher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSeeText('Mi inventario');
});

test('teachers can report events only for students enrolled in their active subjects', function (): void {
    $teacher = teacherPanelUser();
    $admin = User::factory()->administrador()->create();
    $gestion = Gestion::query()->where('codigo', 'II-2026')->firstOrFail();
    $gestion->update(['activa' => true]);
    ConfiguracionAcademica::query()->findOrFail(1)->update(['gestion_vigente' => $gestion->codigo]);
    $subject = teacherPanelSubject('Automation');
    $schedule = teacherPanelSchedule($teacher, $subject);
    $schedule->update([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
    ]);
    $student = teacherPanelStudent('Assigned Student');
    teacherPanelEnrollment($student, $subject, $gestion->codigo)->update(['gestion_id' => $gestion->id]);

    $this->actingAs($teacher)
        ->get(route('panel-docente.seguimiento'))
        ->assertOk()
        ->assertSeeText('Assigned Student')
        ->assertSeeText('Automation')
        ->assertSeeText('Solo administración puede aprobar acciones');

    $this->actingAs($teacher)
        ->post(route('panel-docente.seguimiento.report', $student), [
            'materia_id' => $subject->id,
            'tipo_evento' => 'observacion',
            'descripcion' => 'Requiere apoyo académico.',
            'fecha_evento' => '2026-10-28',
        ])
        ->assertRedirect(route('panel-docente.seguimiento'))
        ->assertSessionHas('success', 'El reporte fue enviado a administración para su revisión.');

    $this->assertDatabaseHas('notificaciones', [
        'user_id' => $admin->id,
        'tipo' => 'seguimiento_docente',
        'titulo' => 'Reporte docente de seguimiento',
        'url' => route('seguimiento-estudiantil.show', $student),
    ]);
    $notification = Notificacion::query()
        ->where('user_id', $admin->id)
        ->where('tipo', 'seguimiento_docente')
        ->firstOrFail();
    expect($notification->mensaje)
        ->toContain('Automation')
        ->toContain('II-2026')
        ->toContain('Observación')
        ->toContain('2026-10-28')
        ->toContain('Requiere apoyo académico.')
        ->toContain('Pendiente de revisión administrativa');
    $this->assertDatabaseMissing('historial_seguimiento_estudiantes', [
        'estudiante_id' => $student->id,
        'descripcion' => 'Requiere apoyo académico.',
    ]);
    expect($student->fresh()->estado)->toBe('activo');
});

test('teachers can filter follow-up students by the semester of their assigned subjects', function (): void {
    $teacher = teacherPanelUser();
    $otherTeacher = teacherPanelUser('Other');
    $gestion = Gestion::query()->where('codigo', 'II-2026')->firstOrFail();
    $gestion->update(['activa' => true]);
    ConfiguracionAcademica::query()->findOrFail(1)->update(['gestion_vigente' => $gestion->codigo]);
    $firstSemesterSubject = teacherPanelSubject('First semester subject');
    $secondSemesterSubject = teacherPanelSubject('Second semester subject');
    $secondSemesterSubject->update(['semestre' => 'Segundo']);
    $otherSemesterSubject = teacherPanelSubject('Other teacher third semester subject');
    $otherSemesterSubject->update(['semestre' => 'Tercero']);

    teacherPanelSchedule($teacher, $firstSemesterSubject)->update([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
    ]);
    teacherPanelSchedule($teacher, $secondSemesterSubject)->update([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'hora_inicio' => '10:00',
        'hora_fin' => '12:00',
    ]);

    teacherPanelSchedule($otherTeacher, $otherSemesterSubject)->update([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
    ]);

    $firstSemesterStudent = teacherPanelStudent('First semester student');
    $secondSemesterStudent = teacherPanelStudent('Second semester student');
    $secondSemesterStudent->update(['semestre' => 'Segundo']);
    teacherPanelEnrollment($firstSemesterStudent, $firstSemesterSubject, $gestion->codigo)->update(['gestion_id' => $gestion->id]);
    teacherPanelEnrollment($secondSemesterStudent, $secondSemesterSubject, $gestion->codigo)->update(['gestion_id' => $gestion->id]);

    $this->actingAs($teacher)
        ->get(route('panel-docente.seguimiento'))
        ->assertOk()
        ->assertSeeText('First semester student')
        ->assertSeeText('Second semester student')
        ->assertSeeText('Primero semestre')
        ->assertSeeText('Segundo semestre')
        ->assertDontSeeText('Tercero semestre');

    $this->actingAs($teacher)
        ->get(route('panel-docente.seguimiento', ['semestre' => 'Segundo']))
        ->assertSeeText('Second semester student')
        ->assertSeeText('Second semester subject')
        ->assertDontSeeText('First semester student')
        ->assertDontSeeText('First semester subject');

    $this->actingAs($teacher)
        ->get(route('panel-docente.seguimiento', ['semestre' => 'Tercero']))
        ->assertSessionHasErrors('semestre');
});

test('teachers cannot report events about students outside their active subjects', function (): void {
    $teacher = teacherPanelUser();
    $admin = User::factory()->administrador()->create();
    $gestion = Gestion::query()->where('codigo', 'II-2026')->firstOrFail();
    $gestion->update(['activa' => true]);
    ConfiguracionAcademica::query()->findOrFail(1)->update(['gestion_vigente' => $gestion->codigo]);
    $otherTeacher = teacherPanelUser('Other');
    $otherSubject = teacherPanelSubject('Networks');
    $otherSchedule = teacherPanelSchedule($otherTeacher, $otherSubject);
    $otherSchedule->update([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
    ]);
    $otherStudent = teacherPanelStudent('Other Teacher Student');
    teacherPanelEnrollment($otherStudent, $otherSubject, $gestion->codigo)->update(['gestion_id' => $gestion->id]);

    $this->actingAs($teacher)
        ->post(route('panel-docente.seguimiento.report', $otherStudent), [
            'materia_id' => $otherSubject->id,
            'tipo_evento' => 'congelamiento',
            'descripcion' => 'Reporte fuera de asignación.',
            'fecha_evento' => '2026-10-28',
        ])
        ->assertNotFound();

    $this->assertDatabaseMissing('notificaciones', [
        'user_id' => $admin->id,
        'tipo' => 'seguimiento_docente',
    ]);
});

test('teachers can poll and clear only their own notification tray', function (): void {
    $teacher = teacherPanelUser();
    $otherTeacher = teacherPanelUser('Other');
    Notificacion::create([
        'user_id' => $teacher->id,
        'tipo' => 'solicitud_equipo',
        'titulo' => 'Solicitud para mis materias',
        'mensaje' => 'Un estudiante solicitó un equipo.',
        'url' => route('solicitudes.equipos.index'),
    ]);
    Notificacion::create([
        'user_id' => $otherTeacher->id,
        'tipo' => 'solicitud_equipo',
        'titulo' => 'Solicitud de otra docente',
        'mensaje' => 'Esta alerta pertenece a otra cuenta.',
        'url' => route('solicitudes.equipos.index'),
    ]);

    $this->actingAs($teacher)
        ->getJson(route('admin.notifications.index'))
        ->assertOk()
        ->assertJsonPath('no_leidas', 1)
        ->assertJsonPath('notificaciones.0.titulo', 'Solicitud para mis materias');

    $this->actingAs($teacher)
        ->postJson(route('admin.notifications.clear'))
        ->assertOk()
        ->assertJsonPath('no_leidas', 0)
        ->assertJsonPath('notificaciones', []);

    $this->assertDatabaseMissing('notificaciones', [
        'user_id' => $teacher->id,
        'titulo' => 'Solicitud para mis materias',
    ]);
    $this->assertDatabaseHas('notificaciones', [
        'user_id' => $otherTeacher->id,
        'titulo' => 'Solicitud de otra docente',
    ]);

    $studentProfile = teacherPanelStudent('Restricted Student');
    $student = User::create([
        'name' => 'Restricted Student',
        'username' => 'restricted-student-notifications',
        'password' => 'password',
        'tipo_usuario' => 'estudiante',
        'estudiante_id' => $studentProfile->id,
    ]);

    $this->actingAs($student)
        ->getJson(route('admin.notifications.index'))
        ->assertForbidden();
});

test('teacher profile and security pages keep the teacher dashboard shell', function (): void {
    $teacher = teacherPanelUser();

    $this->withoutMiddleware(EnsureEmailIsVerified::class)
        ->actingAs($teacher)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('aria-label="Navegación docente"', false)
        ->assertSeeText('Centro docente ITIBB')
        ->assertSeeText('Panel docente')
        ->assertSee('from-[#1D74C9]', false)
        ->assertSeeText('Mi perfil');

    $this->actingAs($teacher)
        ->get(route('security.questions.edit'))
        ->assertOk()
        ->assertSee('aria-label="Navegación docente"', false)
        ->assertSeeText('Centro docente ITIBB')
        ->assertSeeText('Panel docente')
        ->assertSee('from-[#1D74C9]', false)
        ->assertSeeText('Preguntas de seguridad');
});

test('teachers only see diagnostics and monitoring for equipment assigned through their schedules', function (): void {
    $teacher = teacherPanelUser();
    $otherTeacher = teacherPanelUser('Other');
    $subject = teacherPanelSubject('Automation');
    $otherSubject = teacherPanelSubject('Networks');
    $schedule = teacherPanelSchedule($teacher, $subject);
    $otherSchedule = teacherPanelSchedule($otherTeacher, $otherSubject);
    $student = teacherPanelStudent('Assigned Student');
    $ownEquipment = teacherPanelEquipment('OWN-001');
    $otherEquipment = teacherPanelEquipment('OTHER-001');
    teacherPanelAssignment($ownEquipment, $student, $subject, $schedule);
    teacherPanelAssignment($otherEquipment, $student, $otherSubject, $otherSchedule);
    $ownHeartbeat = teacherPanelHeartbeat($ownEquipment);
    $otherHeartbeat = teacherPanelHeartbeat($otherEquipment);
    teacherPanelDiagnostic($ownEquipment, $ownHeartbeat, 'Teacher-visible diagnostic');
    teacherPanelDiagnostic($otherEquipment, $otherHeartbeat, 'Private other-teacher diagnostic');

    $this->actingAs($teacher)
        ->get(route('monitoreo.index'))
        ->assertOk()
        ->assertSeeText('OWN-001')
        ->assertDontSeeText('OTHER-001');

    $this->actingAs($teacher)
        ->get(route('diagnosticos.index'))
        ->assertOk()
        ->assertSeeText('Teacher-visible diagnostic')
        ->assertDontSeeText('Private other-teacher diagnostic')
        ->assertDontSeeText('OTHER-001');
});

test('teachers can browse all equipment and complements in inventory', function (): void {
    $teacher = teacherPanelUser();
    $otherTeacher = teacherPanelUser('Other');
    $subject = teacherPanelSubject('Automation');
    $otherSubject = teacherPanelSubject('Networks');
    $schedule = teacherPanelSchedule($teacher, $subject);
    $otherSchedule = teacherPanelSchedule($otherTeacher, $otherSubject);
    $student = teacherPanelStudent('Assigned Student');
    $ownEquipment = teacherPanelEquipment('OWN-001');
    $otherEquipment = teacherPanelEquipment('OTHER-001');
    $unassignedEquipment = teacherPanelEquipment('UNASSIGNED-001');
    teacherPanelAssignment($ownEquipment, $student, $subject, $schedule);
    teacherPanelAssignment($otherEquipment, $student, $otherSubject, $otherSchedule);
    $complementCategory = Categoria::create([
        'nombre' => 'Teacher complements',
        'grupo' => 'complemento',
        'estado' => 'activa',
    ]);
    $complementAsset = Activo::create([
        'codigo' => 'COM-001',
        'categoria_id' => $complementCategory->id,
        'nombre' => 'Teacher keyboard',
        'estado_uso' => 'disponible',
    ]);
    $complement = Complemento::create([
        'activo_id' => $complementAsset->id,
        'tipo_complemento' => 'Keyboard',
        'tipos_equipo_compatibles' => ['cpu', 'tablet'],
        'estado_fisico' => 'bueno',
    ]);

    $this->actingAs($teacher)
        ->get('/panel-docente/recursos')
        ->assertNotFound();

    $this->actingAs($teacher)
        ->get(route('inventario.index'))
        ->assertOk()
        ->assertSeeText('Complementos')
        ->assertDontSeeText('Registrar activo');

    foreach (['OWN-001', 'OTHER-001', 'UNASSIGNED-001', 'COM-001'] as $code) {
        $this->actingAs($teacher)
            ->get(route('inventario.index', ['buscar' => $code]))
            ->assertSeeText($code);
    }

    $this->actingAs($teacher)
        ->get(route('inventario.activos.show', $ownEquipment->activo_id))
        ->assertOk();

    $this->actingAs($teacher)
        ->get(route('inventario.activos.show', $otherEquipment->activo_id))
        ->assertOk();

    $this->actingAs($teacher)
        ->get(route('inventario.activos.show', $unassignedEquipment->activo_id))
        ->assertOk();

    $this->actingAs($teacher)
        ->get(route('inventario.activos.show', $complement->activo_id))
        ->assertOk();

    $this->actingAs(User::factory()->administrador()->create())
        ->get(route('inventario.activos.create'))
        ->assertOk();
});

test('teachers cannot create inventory assets or resolve and delete incidents', function (): void {
    $teacher = teacherPanelUser();

    $this->actingAs($teacher)
        ->get(route('inventario.activos.create'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->post(route('inventario.activos.store'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->put(route('incidencias.resolver', 999))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->delete(route('incidencias.destroy', 999))
        ->assertForbidden();
});

test('teacher reports include only resources linked to their scheduled assignments', function (): void {
    $teacher = teacherPanelUser();
    $otherTeacher = teacherPanelUser('Other');
    $subject = teacherPanelSubject('Automation');
    $otherSubject = teacherPanelSubject('Networks');
    $schedule = teacherPanelSchedule($teacher, $subject);
    $otherSchedule = teacherPanelSchedule($otherTeacher, $otherSubject);
    $student = teacherPanelStudent('Assigned Student');
    $ownEquipment = teacherPanelEquipment('OWN-001');
    $otherEquipment = teacherPanelEquipment('OTHER-001');
    $unassignedEquipment = teacherPanelEquipment('UNASSIGNED-001');
    $ownAssignment = teacherPanelAssignment($ownEquipment, $student, $subject, $schedule);
    $otherAssignment = teacherPanelAssignment($otherEquipment, $student, $otherSubject, $otherSchedule);
    teacherPanelAccessory($ownEquipment);
    teacherPanelAccessory($otherEquipment);
    teacherPanelAccessory($unassignedEquipment);
    teacherPanelIncident($ownEquipment, $ownAssignment);
    teacherPanelIncident($otherEquipment, $otherAssignment);

    $this->actingAs($teacher)
        ->get(route('reportes.index'))
        ->assertOk()
        ->assertViewHas('estadisticas', function (array $estadisticas): bool {
            return $estadisticas['totalEquipos'] === 1
                && $estadisticas['totalAccesorios'] === 1
                && $estadisticas['totalAsignaciones'] === 1
                && $estadisticas['totalIncidencias'] === 1;
        })
        ->assertDontSeeText('OTHER-001')
        ->assertDontSeeText('UNASSIGNED-001');
});

function teacherPanelUser(string $suffix = 'Current'): User
{
    $profile = teacherPanelProfile($suffix);

    return User::create([
        'name' => "Teacher {$suffix}",
        'username' => strtolower("teacher-{$suffix}"),
        'password' => 'password',
        'tipo_usuario' => 'docente',
        'docente_id' => $profile->id,
    ]);
}

function teacherPanelProfile(string $suffix): Docente
{
    return Docente::create([
        'nombres' => $suffix,
        'apellido_paterno' => 'Teacher',
        'apellido_materno' => 'Profile',
        'ci' => "CI-{$suffix}",
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
}

function teacherPanelStudent(string $name): Estudiante
{
    return Estudiante::create([
        'nombres' => $name,
        'apellido_paterno' => 'Student',
        'apellido_materno' => 'Profile',
        'matricula' => strtoupper(str_replace(' ', '-', $name)),
        'semestre' => 'Primero',
        'ci' => fake()->unique()->numerify('########'),
        'telefono' => '71112222',
        'estado' => 'activo',
    ]);
}

function teacherPanelSubject(string $name): Materia
{
    return Materia::create([
        'nombre' => $name,
        'sigla' => strtoupper(substr($name, 0, 4)),
        'semestre' => 'Primero',
        'estado' => 'activo',
    ]);
}

function teacherPanelSchedule(User $teacher, Materia $subject): Horario
{
    return Horario::create([
        'docente_id' => $teacher->docente_id,
        'materia_id' => $subject->id,
        'dia_semana' => 'Lunes',
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => 'activo',
    ]);
}

function teacherPanelEnrollment(
    Estudiante $student,
    Materia $subject,
    string $gestion,
    string $estado = 'Cursando',
): Inscripcion {
    return Inscripcion::create([
        'estudiante_id' => $student->id,
        'materia_id' => $subject->id,
        'gestion' => $gestion,
        'tipo' => 'Normal',
        'estado' => $estado,
        'fecha_inscripcion' => now()->toDateString(),
    ]);
}

function teacherPanelEquipment(string $code): Equipo
{
    $category = Categoria::create([
        'nombre' => "Teacher equipment {$code}",
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => $code,
        'categoria_id' => $category->id,
        'nombre' => "Equipment {$code}",
        'estado_uso' => 'disponible',
    ]);

    return Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => $code,
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
}

function teacherPanelAssignment(
    Equipo $equipment,
    Estudiante $student,
    Materia $subject,
    Horario $schedule,
): Asignacion {
    return Asignacion::create([
        'codigo' => fake()->unique()->bothify('ASG-######'),
        'tipo' => 'asignado',
        'equipo_id' => $equipment->id,
        'estudiante_id' => $student->id,
        'horario_id' => $schedule->id,
        'origen' => 'panel_web',
        'autorizado_por' => User::factory()->administrador()->create()->id,
        'fecha_entrega' => now(),
        'fecha_devolucion_prevista' => now()->addDays(30),
        'estado' => 'activa',
    ]);
}

function teacherPanelAccessory(Equipo $equipment): Accesorio
{
    return Accesorio::create([
        'equipo_id' => $equipment->id,
        'nombre' => 'Teclado de prueba',
        'cantidad' => 1,
        'estado' => 'disponible',
    ]);
}

function teacherPanelIncident(Equipo $equipment, Asignacion $assignment): Incidencia
{
    return Incidencia::create([
        'equipo_id' => $equipment->id,
        'asignacion_id' => $assignment->id,
        'tipo' => 'daño',
        'descripcion' => 'Incidencia de prueba',
        'prioridad' => 'media',
        'estado' => 'pendiente',
        'fecha_reporte' => now(),
    ]);
}

function teacherPanelHeartbeat(Equipo $equipment): Heartbeat
{
    return Heartbeat::create([
        'equipo_id' => $equipment->id,
        'fecha_hora' => now(),
    ]);
}

function teacherPanelDiagnostic(Equipo $equipment, Heartbeat $heartbeat, string $description): Diagnostico
{
    return Diagnostico::create([
        'equipo_id' => $equipment->id,
        'heartbeat_id' => $heartbeat->id,
        'tipo' => 'salud',
        'nivel' => 'Crítico',
        'descripcion' => $description,
        'fecha_hora' => now(),
    ]);
}
