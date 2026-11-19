<?php

use App\Models\Bitacora;
use App\Models\ConfiguracionAcademica;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\HistorialSeguimientoEstudiante;
use App\Models\Horario;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('administrators can manage subjects schedules and enrollments', function () {
    $admin = academicAdminActor();
    $teacher = academicTeacherProfile();
    $student = academicStudentProfile();
    $gestion = academicGestionCodigo();

    $this->actingAs($admin)
        ->post(route('materias.store'), [
            'nombre' => 'Programación Industrial',
            'sigla' => 'PROG-I',
            'semestre' => 'Primero',
            'gestion' => $gestion,
        ])
        ->assertRedirect(route('gestion-academica.semestre', ['semestre' => 'Primero', 'gestion' => $gestion]));

    $materia = Materia::where('nombre', 'Programación Industrial')->firstOrFail();
    $this->actingAs($admin)
        ->put(route('materias.update', $materia), [
            'nombre' => 'Programación Industrial II',
            'sigla' => 'PROG-II',
            'semestre' => 'Segundo',
            'gestion' => $gestion,
        ])
        ->assertRedirect(route('gestion-academica.semestre', ['semestre' => 'Segundo', 'gestion' => $gestion]));

    $this->assertDatabaseHas('materias', [
        'id' => $materia->id,
        'nombre' => 'Programación Industrial II',
        'sigla' => 'PROG-II',
        'semestre' => 'Segundo',
    ]);
    $student->update(['semestre' => 'Segundo']);

    $this->actingAs($admin)
        ->get(route('gestion-academica.semestre', ['semestre' => 'Segundo', 'gestion' => $gestion]))
        ->assertSeeText('PROG-II');

    $this->actingAs($admin)
        ->post(route('horarios.store'), [
            'docente_id' => $teacher->id,
            'materia_id' => $materia->id,
            'dia_semana' => 'Lunes',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
            'aula' => 'Lab 1',
            'gestion' => $gestion,
        ])
        ->assertRedirect(route('gestion-academica.semestre', ['semestre' => 'Segundo', 'gestion' => $gestion]));

    $horario = Horario::where('materia_id', $materia->id)->firstOrFail();
    $this->actingAs($admin)
        ->patch(route('horarios.status', $horario), ['estado' => 'inactivo', 'gestion' => $gestion])
        ->assertRedirect(route('gestion-academica.materia', [
            'semestre' => 'Segundo',
            'materia' => $materia,
            'gestion' => $gestion,
        ]))
        ->assertSessionHas('success', 'Estado del horario actualizado correctamente.');
    $this->assertDatabaseHas('horarios', [
        'id' => $horario->id,
        'estado' => 'inactivo',
    ]);
    $this->actingAs($admin)
        ->get(route('gestion-academica.materia', [
            'semestre' => 'Segundo',
            'materia' => $materia,
            'gestion' => $gestion,
        ]))
        ->assertSeeText('Inactivo')
        ->assertSee('bg-emerald-600 hover:bg-emerald-700', false);

    $this->actingAs($admin)
        ->patch(route('horarios.status', $horario), ['estado' => 'activo', 'gestion' => $gestion])
        ->assertRedirect(route('gestion-academica.materia', [
            'semestre' => 'Segundo',
            'materia' => $materia,
            'gestion' => $gestion,
        ]));
    $this->assertDatabaseHas('horarios', [
        'id' => $horario->id,
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $materia->id,
            'gestion' => $gestion,
            'tipo' => 'Normal',
            'fecha_inscripcion' => '2026-10-14',
        ])
        ->assertRedirect(route('gestion-academica.materia', [
            'semestre' => 'Segundo',
            'materia' => $materia,
            'gestion' => $gestion,
        ]));

    $inscripcion = Inscripcion::where('estudiante_id', $student->id)->firstOrFail();
    $this->actingAs($admin)
        ->get(route('gestion-academica.materia', [
            'semestre' => 'Segundo',
            'materia' => $materia,
            'gestion' => $gestion,
        ]))
        ->assertOk()
        ->assertSeeText('Horarios y docentes')
        ->assertSeeText('Estudiantes inscritos')
        ->assertSeeText($student->matricula)
        ->assertSee('bg-blue-50/70', false)
        ->assertSee('text-slate-600', false)
        ->assertSeeText('Volver al semestre')
        ->assertSeeInOrder(['Consultar gestión', 'Materia activa', 'Volver al semestre'])
        ->assertSee('text-[#272F7A]', false);

    $this->actingAs($admin)
        ->from(route('gestion-academica.semestre', ['semestre' => 'Segundo', 'gestion' => $gestion]))
        ->patch(route('inscripciones.status', $inscripcion), ['estado' => 'Inactivo'])
        ->assertSessionHasErrors('estado');
    $this->assertDatabaseHas('inscripciones', [
        'id' => $inscripcion->id,
        'estado' => 'Cursando',
    ]);
});

test('administrators cannot assign a sigla already used by another subject', function () {
    $admin = academicAdminActor();
    $existingSubject = Materia::create([
        'nombre' => 'Programación Industrial',
        'sigla' => 'PROG-I',
        'semestre' => 'Primero',
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->from(route('materias.create', ['semestre' => 'Primero']))
        ->post(route('materias.store'), [
            'nombre' => 'Programación Aplicada',
            'sigla' => 'PROG-I',
            'semestre' => 'Primero',
        ])
        ->assertSessionHasErrors('sigla');

    $this->assertDatabaseCount('materias', 1);
    $this->assertDatabaseHas('materias', [
        'id' => $existingSubject->id,
        'sigla' => 'PROG-I',
    ]);
});

test('administrators can create a subject without a sigla', function () {
    $admin = academicAdminActor();

    $this->actingAs($admin)
        ->post(route('materias.store'), [
            'nombre' => 'Materia sin sigla',
            'semestre' => 'Primero',
            'gestion' => academicGestionCodigo(),
        ])
        ->assertRedirect(route('gestion-academica.semestre', [
            'semestre' => 'Primero',
            'gestion' => academicGestionCodigo(),
        ]));

    $this->assertDatabaseHas('materias', [
        'nombre' => 'Materia sin sigla',
        'sigla' => null,
    ]);
});

test('administrators can remove a subject sigla', function () {
    $admin = academicAdminActor();
    $subject = academicSubject('Materia con sigla');
    $subject->update(['sigla' => 'MAT-1']);

    $this->actingAs($admin)
        ->put(route('materias.update', $subject), [
            'nombre' => 'Materia con sigla',
            'sigla' => '',
            'semestre' => 'Primero',
            'gestion' => academicGestionCodigo(),
        ])
        ->assertRedirect(route('gestion-academica.semestre', [
            'semestre' => 'Primero',
            'gestion' => academicGestionCodigo(),
        ]));

    $this->assertDatabaseHas('materias', [
        'id' => $subject->id,
        'sigla' => null,
    ]);
});

test('schedule and enrollment period codes follow their linked academic period', function () {
    $gestion = Gestion::activa();
    $teacher = academicTeacherProfile();
    $student = academicStudentProfile();
    $subject = academicSubject('Materia con gestión sincronizada');

    $schedule = Horario::create([
        'gestion_id' => $gestion->id,
        'gestion' => 'I-1900',
        'docente_id' => $teacher->id,
        'materia_id' => $subject->id,
        'dia_semana' => 'Lunes',
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => 'activo',
    ]);
    $enrollment = Inscripcion::create([
        'estudiante_id' => $student->id,
        'materia_id' => $subject->id,
        'gestion_id' => $gestion->id,
        'gestion' => 'I-1900',
        'tipo' => 'Normal',
        'estado' => 'Cursando',
        'fecha_inscripcion' => '2026-10-14',
    ]);

    $this->assertDatabaseHas('horarios', [
        'id' => $schedule->id,
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
    ]);
    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
    ]);
});

test('administrators cannot create or update schedules for weekends', function () {
    $admin = academicAdminActor();
    $teacher = academicTeacherProfile();
    $subject = academicSubject('Automatización Industrial');
    $schedule = academicSchedule($teacher, $subject);

    $this->actingAs($admin)
        ->get(route('horarios.create'))
        ->assertSeeText('Lunes')
        ->assertSeeText('Viernes')
        ->assertDontSeeText('Sabado')
        ->assertDontSeeText('Domingo');

    $this->actingAs($admin)
        ->from(route('horarios.create'))
        ->post(route('horarios.store'), [
            'docente_id' => $teacher->id,
            'materia_id' => $subject->id,
            'dia_semana' => 'Sabado',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ])
        ->assertSessionHasErrors('dia_semana');

    $this->actingAs($admin)
        ->from(route('horarios.edit', $schedule))
        ->put(route('horarios.update', $schedule), [
            'docente_id' => $teacher->id,
            'materia_id' => $subject->id,
            'dia_semana' => 'Domingo',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ])
        ->assertSessionHasErrors('dia_semana');

    $this->assertDatabaseCount('horarios', 1);
    $this->assertDatabaseHas('horarios', [
        'id' => $schedule->id,
        'dia_semana' => 'Lunes',
    ]);
});

test('academic create and edit forms share the user form visual header', function () {
    $admin = academicAdminActor();
    $teacher = academicTeacherProfile();
    $student = academicStudentProfile();
    $subject = academicSubject('Gestión de Procesos');
    $schedule = academicSchedule($teacher, $subject);
    $enrollment = academicEnrollment($student, $subject);

    $this->actingAs($admin)
        ->get(route('materias.create', ['semestre' => 'Primero']))
        ->assertSeeText('Crear materia')
        ->assertSeeText('Volver al semestre')
        ->assertSee('dashboard-grid', false);

    $materiaDetalleUrl = route('gestion-academica.materia', [
        'semestre' => $subject->semestre,
        'materia' => $subject,
    ]);
    $response = $this->actingAs($admin)
        ->get(route('materias.edit', $subject))
        ->assertSeeText('Editar materia')
        ->assertSeeText('Volver a la materia')
        ->assertSee($materiaDetalleUrl, false)
        ->assertSee('dashboard-grid', false);
    expect(substr_count($response->getContent(), 'href="'.$materiaDetalleUrl.'"'))->toBe(2);

    $materiaDetalleUrl = route('gestion-academica.materia', [
        'semestre' => $subject->semestre,
        'materia' => $subject,
        'gestion' => academicGestionCodigo(),
    ]);
    $response = $this->actingAs($admin)
        ->get(route('horarios.create', [
            'semestre' => $subject->semestre,
            'materia_id' => $subject,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertSeeText('Volver a la materia')
        ->assertSee($materiaDetalleUrl, false);
    expect(substr_count($response->getContent(), 'href="'.$materiaDetalleUrl.'"'))->toBe(2);

    $response = $this->actingAs($admin)
        ->get(route('horarios.edit', [
            'horario' => $schedule,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertSeeText('Volver a la materia')
        ->assertSee($materiaDetalleUrl, false);
    expect(substr_count($response->getContent(), 'href="'.$materiaDetalleUrl.'"'))->toBe(2);

    $this->actingAs($admin)
        ->get(route('horarios.create'))
        ->assertSeeText('Crear horario')
        ->assertSee('dashboard-grid', false);

    $this->actingAs($admin)
        ->get(route('horarios.edit', $schedule))
        ->assertSeeText('Editar horario')
        ->assertSee('dashboard-grid', false);

    $this->actingAs($admin)
        ->get(route('inscripciones.create'))
        ->assertSeeText('Registrar inscripción')
        ->assertSee('dashboard-grid', false);

    $this->actingAs($admin)
        ->get('/inscripciones/'.$enrollment->id.'/editar')
        ->assertNotFound();
});

test('administrators can toggle a subject status and stay on its detail page', function () {
    $admin = academicAdminActor();
    $subject = academicSubject('Mantenimiento Industrial');
    $gestion = academicGestionCodigo();
    $detailUrl = route('gestion-academica.materia', [
        'semestre' => $subject->semestre,
        'materia' => $subject,
        'gestion' => $gestion,
    ]);

    $this->actingAs($admin)
        ->patch(route('materias.status', $subject), [
            'estado' => 'inactivo',
            'gestion' => $gestion,
        ])
        ->assertRedirect($detailUrl)
        ->assertSessionHas('success', 'Estado de la materia actualizado correctamente.');

    $this->assertDatabaseHas('materias', [
        'id' => $subject->id,
        'estado' => 'inactivo',
    ]);

    $this->actingAs($admin)
        ->get($detailUrl)
        ->assertSeeText('Materia inactiva')
        ->assertSee('Estado de la materia actualizado correctamente.', false)
        ->assertDontSee('rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800', false)
        ->assertSee('bg-emerald-600 hover:bg-emerald-700', false);

    $this->actingAs($admin)
        ->patch(route('materias.status', $subject), [
            'estado' => 'activo',
            'gestion' => $gestion,
        ])
        ->assertRedirect($detailUrl);

    $this->assertDatabaseHas('materias', [
        'id' => $subject->id,
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->get($detailUrl)
        ->assertSeeText('Materia activa')
        ->assertSee('bg-rose-600 hover:bg-rose-700', false);
});

test('long subject names wrap in the subject detail header without squeezing its controls', function () {
    $admin = academicAdminActor();
    $subject = academicSubject('SISTEMAS OPERATIVOS Y TECNOLOGIA DE LA INFORMACIÓN');

    $this->actingAs($admin)
        ->get(route('gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $subject,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertSeeText('SISTEMAS OPERATIVOS Y TECNOLOGIA DE LA INFORMACIÓN')
        ->assertSee('w-full min-w-0', false)
        ->assertSee('whitespace-normal break-normal', false)
        ->assertSeeText('Materia activa')
        ->assertSeeText('Volver al semestre');
});

test('teachers can only view academic records in their assigned schedules', function () {
    $teacher = academicTeacherProfile();
    $otherTeacher = academicTeacherProfile();
    $teacherUser = academicTeacherActor($teacher);
    $student = academicStudentProfile();
    $otherStudent = academicStudentProfile();
    $assignedSubject = academicSubject('Control Industrial');
    $otherSubject = academicSubject('Electrónica Avanzada');

    $assignedSchedule = academicSchedule($teacher, $assignedSubject);
    academicSchedule($otherTeacher, $otherSubject);
    $assignedEnrollment = academicEnrollment($student, $assignedSubject);
    academicEnrollment($otherStudent, $otherSubject);

    $this->actingAs($teacherUser)
        ->get(route('panel-docente.gestion-academica.semestre', ['semestre' => 'Primero', 'gestion' => academicGestionCodigo()]))
        ->assertOk()
        ->assertSeeText('Control Industrial')
        ->assertDontSeeText('Electrónica Avanzada')
        ->assertSee('text-[#272F7A]', false)
        ->assertSee(route('panel-docente.gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $assignedSubject->id,
            'gestion' => academicGestionCodigo(),
        ]), false);

    $this->actingAs($teacherUser)
        ->get(route('panel-docente.gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $assignedSubject->id,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertOk()
        ->assertSeeText($student->matricula)
        ->assertDontSeeText($otherStudent->matricula)
        ->assertSeeText('Volver al semestre');

    $this->actingAs($teacherUser)
        ->get(route('gestion-academica.semestre', [
            'semestre' => 'Primero',
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertForbidden();

    $this->actingAs($teacherUser)
        ->get(route('panel-docente.gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $otherSubject->id,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertNotFound();

    $this->actingAs($teacherUser)
        ->get(route('materias.create', ['semestre' => 'Primero']))
        ->assertForbidden();

    $this->actingAs($teacherUser)
        ->patch(route('horarios.status', $assignedSchedule), ['estado' => 'inactivo'])
        ->assertForbidden();

    $this->actingAs($teacherUser)
        ->patch(route('inscripciones.register-partial', $student->inscripciones()->firstOrFail()), [
            'parcial' => '1',
            'nota' => 80,
        ])
        ->assertRedirect();

    $this->actingAs($teacherUser)
        ->patch(route('inscripciones.register-partial', $student->inscripciones()->firstOrFail()), [
            'parcial' => '2',
            'nota' => 90,
        ])
        ->assertRedirect();

    $this->actingAs($teacherUser)
        ->patch(route('panel-docente.inscripciones.report-results', $assignedSubject))
        ->assertRedirect();

    $this->assertDatabaseHas('inscripciones', [
        'id' => $assignedEnrollment->id,
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $teacherUser->id,
        'estado' => 'Cursando',
    ]);

    $otherTeacherUser = academicTeacherActor($otherTeacher);
    $this->actingAs($otherTeacherUser)
        ->patch(route('panel-docente.inscripciones.report-results', $assignedSubject))
        ->assertNotFound();

    $admin = academicAdminActor();
    $inscripcion = $student->inscripciones()->firstOrFail();
    $this->actingAs($admin)
        ->patch(route('inscripciones.status', $inscripcion), ['estado' => 'Aprobado'])
        ->assertRedirect(route('gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $assignedSubject->id,
            'gestion' => academicGestionCodigo(),
        ]));

    $this->assertDatabaseHas('inscripciones', [
        'id' => $inscripcion->id,
        'estado' => 'Aprobado',
        'revisado_por' => $admin->id,
    ]);
    expect($student->fresh()->semestre)->toBe('Primero');
});

test('administrators with a teacher profile can grade only their assigned subjects', function () {
    $admin = academicAdminActor();
    $adminTeacher = Docente::query()->findOrFail($admin->docente_id);
    $otherTeacher = academicTeacherProfile();
    $assignedSubject = academicSubject('Automatización Aplicada');
    $unassignedSubject = academicSubject('Electrónica Digital');
    $assignedStudent = academicStudentProfile();
    $unassignedStudent = academicStudentProfile();

    academicSchedule($adminTeacher, $assignedSubject);
    academicSchedule($otherTeacher, $unassignedSubject);
    $assignedEnrollment = academicEnrollment($assignedStudent, $assignedSubject);
    $unassignedEnrollment = academicEnrollment($unassignedStudent, $unassignedSubject);

    $this->actingAs($admin)
        ->get(route('gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $assignedSubject,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertSee(route('inscripciones.register-partial', $assignedEnrollment), false);

    $this->actingAs($admin)
        ->patch(route('inscripciones.register-partial', $assignedEnrollment), [
            'parcial' => '1',
            'nota' => 85,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('inscripciones', [
        'id' => $assignedEnrollment->id,
        'parcial_1' => 85,
        'parcial_1_por' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $unassignedSubject,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertDontSee('name="nota"', false);

    $this->actingAs($admin)
        ->patch(route('inscripciones.register-partial', $unassignedEnrollment), [
            'parcial' => '1',
            'nota' => 90,
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('inscripciones', [
        'id' => $unassignedEnrollment->id,
        'parcial_1' => null,
    ]);
});

test('an administrator cannot create duplicate enrollments for a student and subject', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');
    academicEnrollment($student, $subject);

    $this->actingAs($admin)
        ->from(route('inscripciones.create'))
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $subject->id,
            'gestion' => academicGestionCodigo(),
            'tipo' => 'Normal',
            'fecha_inscripcion' => '2026-10-14',
        ])
        ->assertSessionHasErrors([
            'materia_id' => 'El estudiante ya tiene una inscripción registrada en esta materia durante esta gestión.',
        ]);

    $this->actingAs($admin)
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $subject->id,
            'gestion' => academicGestionCodigo(),
            'tipo' => 'Normal',
            'fecha_inscripcion' => '2026-10-14',
        ])
        ->assertSessionHasErrors('materia_id');

    $this->assertDatabaseCount('inscripciones', 1);
});

test('administrators cannot enroll inactive students', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $student->update(['estado' => 'inactivo']);
    $subject = academicSubject('Redes Industriales');
    academicSchedule(academicTeacherProfile(), $subject);

    $this->actingAs($admin)
        ->from(route('inscripciones.create'))
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $subject->id,
            'gestion' => academicGestionCodigo(),
            'tipo' => 'Normal',
            'fecha_inscripcion' => '2026-10-14',
        ])
        ->assertSessionHasErrors([
            'estudiante_id' => 'El estudiante no está activo; sus registros académicos permanecen pausados hasta su reincorporación.',
        ]);

    $this->assertDatabaseCount('inscripciones', 0);
});

test('an observation in student follow-up does not block enrollment', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Materia con observación');
    academicSchedule(academicTeacherProfile(), $subject);
    $gestion = Gestion::activa();
    HistorialSeguimientoEstudiante::create([
        'estudiante_id' => $student->id,
        'gestion_id' => $gestion->id,
        'tipo_evento' => 'observacion',
        'descripcion' => 'Seguimiento académico sin suspensión.',
        'fecha_evento' => now()->toDateString(),
        'registrado_por' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $subject->id,
            'gestion' => $gestion->codigo,
            'tipo' => 'Normal',
            'fecha_inscripcion' => now()->toDateString(),
        ])
        ->assertRedirect(route('gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $subject,
            'gestion' => $gestion->codigo,
        ]));

    $this->assertDatabaseHas('inscripciones', [
        'gestion_id' => $gestion->id,
        'estudiante_id' => $student->id,
        'materia_id' => $subject->id,
        'estado' => 'Cursando',
    ]);
});

test('administrators cannot enroll students in a subject from another semester', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $student->update(['semestre' => 'Segundo']);
    $subject = academicSubject('Redes Industriales');
    academicSchedule(academicTeacherProfile(), $subject);

    $this->actingAs($admin)
        ->from(route('inscripciones.create'))
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $subject->id,
            'gestion' => academicGestionCodigo(),
            'tipo' => 'Normal',
            'fecha_inscripcion' => '2026-10-14',
        ])
        ->assertSessionHasErrors([
            'materia_id' => 'El semestre de la materia debe coincidir con el semestre del estudiante.',
        ]);

    $this->assertDatabaseCount('inscripciones', 0);
});

test('administrators cannot enroll students in inactive subjects', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');
    $subject->update(['estado' => 'inactivo']);

    $this->actingAs($admin)
        ->from(route('inscripciones.create'))
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $subject->id,
            'gestion' => academicGestionCodigo(),
            'tipo' => 'Normal',
            'fecha_inscripcion' => '2026-10-14',
        ])
        ->assertSessionHasErrors([
            'materia_id' => 'La materia debe estar activa para registrar la inscripción.',
        ]);

    $this->assertDatabaseCount('inscripciones', 0);
});

test('administrators cannot enroll students in subjects without an active schedule', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');

    $this->actingAs($admin)
        ->from(route('inscripciones.create'))
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $subject->id,
            'gestion' => academicGestionCodigo(),
            'tipo' => 'Normal',
            'fecha_inscripcion' => '2026-10-14',
        ])
        ->assertSessionHasErrors([
            'materia_id' => 'La materia debe tener un horario activo en la gestión vigente para inscribir estudiantes.',
        ]);

    $this->assertDatabaseCount('inscripciones', 0);
});

test('administrators cannot enroll students when a subject only has a schedule in another period', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');
    $schedule = academicSchedule(academicTeacherProfile(), $subject);
    $previousGestion = Gestion::query()->where('codigo', 'I-2026')->firstOrFail();
    $schedule->update(['gestion_id' => $previousGestion->id]);

    $this->actingAs($admin)
        ->from(route('inscripciones.create'))
        ->post(route('inscripciones.store'), [
            'estudiante_id' => $student->id,
            'materia_id' => $subject->id,
            'gestion' => academicGestionCodigo(),
            'tipo' => 'Normal',
            'fecha_inscripcion' => '2026-10-14',
        ])
        ->assertSessionHasErrors([
            'materia_id' => 'La materia debe tener un horario activo en la gestión vigente para inscribir estudiantes.',
        ]);

    $this->assertDatabaseCount('inscripciones', 0);
});

test('administrators cannot open an enrollment edit screen', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');
    academicSchedule(academicTeacherProfile(), $subject);
    $enrollment = academicEnrollment($student, $subject);

    $this->actingAs($admin)
        ->get('/inscripciones/'.$enrollment->id.'/editar')
        ->assertNotFound();

    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'estudiante_id' => $student->id,
        'estado' => 'Cursando',
    ]);
});

test('administrators cannot update an enrollment through the removed general update route', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');
    academicSchedule(academicTeacherProfile(), $subject);
    $enrollment = academicEnrollment($student, $subject);
    $enrollment->update(['parcial_1' => 80]);

    $this->actingAs($admin)
        ->put('/inscripciones/'.$enrollment->id, [
            'estudiante_id' => academicStudentProfile()->id,
            'materia_id' => $subject->id,
            'gestion' => academicGestionCodigo(),
            'tipo' => 'Normal',
            'fecha_inscripcion' => '2026-10-14',
            'estado' => 'Cursando',
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'estudiante_id' => $student->id,
        'materia_id' => $subject->id,
        'parcial_1' => 80,
    ]);
});

test('grade corrections cannot change the student or subject identity', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $replacementStudent = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');
    $replacementSubject = academicSubject('Electrónica Aplicada');
    academicSchedule(academicTeacherProfile(), $subject);
    academicSchedule(academicTeacherProfile(), $replacementSubject);
    $enrollment = academicEnrollment($student, $subject);
    $enrollment->update([
        'parcial_1' => 40,
        'parcial_2' => 50,
        'nota_final' => 45,
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
    ]);

    $this->actingAs($admin)
        ->patch(route('inscripciones.corregir-notas', $enrollment), [
            'parcial_1' => 80,
            'parcial_2' => 90,
            'motivo' => 'Corrección de notas autorizada.',
            'estudiante_id' => $replacementStudent->id,
            'materia_id' => $replacementSubject->id,
        ])
        ->assertRedirect(route('gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $subject,
            'gestion' => academicGestionCodigo(),
        ]));

    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'estudiante_id' => $student->id,
        'materia_id' => $subject->id,
        'parcial_1' => 80,
        'parcial_2' => 90,
        'nota_final' => 85,
        'resultado_reportado' => 'Aprobado',
        'resultado_final' => null,
        'estado' => 'Cursando',
    ]);
    $this->assertDatabaseHas('bitacora', [
        'accion' => 'corregir_notas_administracion',
        'entidad' => 'inscripciones',
        'entidad_id' => $enrollment->id,
    ]);
    $auditoria = Bitacora::query()
        ->where('accion', 'corregir_notas_administracion')
        ->where('entidad_id', $enrollment->id)
        ->firstOrFail();

    expect($auditoria->user_id)->toBe($admin->id)
        ->and($auditoria->despues['motivo'])->toBe('Corrección de notas autorizada.');
});

test('administrators cannot correct grades before the teacher reports the result', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Materia sin reporte');
    academicSchedule(academicTeacherProfile(), $subject);
    $enrollment = academicEnrollment($student, $subject);
    $enrollment->update([
        'parcial_1' => 40,
        'parcial_2' => 50,
        'nota_final' => 45,
    ]);

    $this->actingAs($admin)
        ->patch(route('inscripciones.corregir-notas', $enrollment), [
            'parcial_1' => 60,
            'parcial_2' => 70,
            'motivo' => 'Corrección de evaluación.',
        ])
        ->assertSessionHasErrors([
            'notas' => 'Solo se pueden corregir notas después de que el docente reporte los resultados.',
        ]);

    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'parcial_1' => 40,
        'parcial_2' => 50,
        'nota_final' => 45,
        'resultado_reportado' => null,
    ]);
});

test('correcting reported grades invalidates the previously confirmed progression decision', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Materia con corrección posterior');
    academicSchedule(academicTeacherProfile(), $subject);
    $enrollment = academicEnrollment($student, $subject);
    $enrollment->update([
        'parcial_1' => 70,
        'parcial_2' => 80,
        'nota_final' => 75,
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
        'estado' => 'Aprobado',
        'resultado_final' => 'Aprobado',
        'revisado_por' => $admin->id,
        'fecha_revision' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('gestion-academica.progresion.confirmar', $student), [
            'decision' => 'avanza',
        ])
        ->assertRedirect(route('gestion-academica.progresion'));

    $this->actingAs($admin)
        ->patch(route('inscripciones.corregir-notas', $enrollment), [
            'parcial_1' => 30,
            'parcial_2' => 40,
            'motivo' => 'Se corrigió un error de transcripción.',
        ])
        ->assertSessionHas('success');

    $this->assertDatabaseHas('decisiones_progresion', [
        'gestion_id' => Gestion::activa()->id,
        'estudiante_id' => $student->id,
        'decision_recomendada' => 'avanza',
        'decision_confirmada' => null,
        'revisado_por' => null,
    ]);
    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'nota_final' => 35,
        'resultado_reportado' => 'Reprobado',
        'resultado_final' => null,
        'estado' => 'Cursando',
    ]);
});

test('administrators cannot update an enrollment before grading starts', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Materia sin notas');
    academicSchedule(academicTeacherProfile(), $subject);
    $enrollment = academicEnrollment($student, $subject);

    $this->actingAs($admin)
        ->put('/inscripciones/'.$enrollment->id, [
            'estudiante_id' => academicStudentProfile()->id,
            'materia_id' => $subject->id,
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'estudiante_id' => $student->id,
        'materia_id' => $subject->id,
        'parcial_1' => null,
        'resultado_reportado' => null,
    ]);
});

test('administrators review reported outcomes without a general enrollment edit action', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');
    academicSchedule(academicTeacherProfile(), $subject);
    $enrollment = academicEnrollment($student, $subject);
    $enrollment->update([
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $subject,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertSeeText('Confirmar')
        ->assertDontSeeText('Editar inscripción')
        ->assertSeeText('Corregir notas reportadas');
});

test('administrative confirmation marks the reported result as reviewed for administrators and teachers', function () {
    $admin = academicAdminActor();
    $teacher = academicTeacherProfile();
    $teacherUser = academicTeacherActor($teacher);
    $student = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');
    academicSchedule($teacher, $subject);
    $enrollment = academicEnrollment($student, $subject);
    $enrollment->update([
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $teacherUser->id,
        'fecha_reporte' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $subject,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertSeeText('En revisión');

    $this->actingAs($admin)
        ->patch(route('inscripciones.status', $enrollment), ['estado' => 'Aprobado'])
        ->assertRedirect();

    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'resultado_final' => 'Aprobado',
        'revisado_por' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $subject,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertSeeText('Revisado')
        ->assertSeeText('Resultado oficial: Aprobado');

    $this->actingAs($teacherUser)
        ->get(route('panel-docente.gestion-academica.materia', [
            'semestre' => 'Primero',
            'materia' => $subject,
            'gestion' => academicGestionCodigo(),
        ]))
        ->assertSeeText('Revisado')
        ->assertSeeText('Resultado oficial: Aprobado');
});

test('students can see official course results in their current follow-up', function () {
    $student = academicStudentProfile();
    $studentUser = User::factory()->estudiante($student)->create();
    $subject = academicSubject('Materia con resultado oficial');
    academicSchedule(academicTeacherProfile(), $subject);
    $enrollment = academicEnrollment($student, $subject);
    $admin = academicAdminActor();
    $enrollment->update([
        'parcial_1' => 80,
        'parcial_2' => 70,
        'nota_final' => 75,
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
        'estado' => 'Aprobado',
        'resultado_final' => 'Aprobado',
        'revisado_por' => $admin->id,
        'fecha_revision' => now(),
    ]);

    $this->actingAs($studentUser, 'student')
        ->get(route('estudiante.followup.index'))
        ->assertSeeText('Tu progreso académico')
        ->assertSeeText('Materias inscritas')
        ->assertSeeText('En curso')
        ->assertSeeText('Aprobadas')
        ->assertSeeText('Reprobadas')
        ->assertSeeText('Materia con resultado oficial')
        ->assertSeeText('P1: 80.00')
        ->assertSeeText('P2: 70.00')
        ->assertSeeText('Final: 75.00')
        ->assertSeeText('Aprobado');
});

test('administrators confirm progression from reviewed grades and students see that decision', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $studentUser = User::factory()->estudiante($student)->create();
    $approvedSubject = academicSubject('Materia aprobada para progresión');
    $failedSubject = academicSubject('Materia reprobada para progresión');
    $approvedEnrollment = academicEnrollment($student, $approvedSubject);
    $failedEnrollment = academicEnrollment($student, $failedSubject);

    foreach ([
        [$approvedEnrollment, 'Aprobado', 80],
        [$failedEnrollment, 'Reprobado', 45],
    ] as [$enrollment, $result, $grade]) {
        $enrollment->update([
            'parcial_1' => $grade,
            'parcial_2' => $grade,
            'nota_final' => $grade,
            'resultado_reportado' => $result,
            'resultado_reportado_por' => $admin->id,
            'fecha_reporte' => now(),
            'estado' => $result,
            'resultado_final' => $result,
            'revisado_por' => $admin->id,
            'fecha_revision' => now(),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('gestion-academica.progresion'))
        ->assertSeeText('Materias aprobadas: 1 · Reprobadas: 1')
        ->assertSeeText('Avanza con materias de arrastre');

    $this->actingAs($admin)
        ->post(route('gestion-academica.progresion.confirmar', $student), [
            'decision' => 'avanza_con_arrastres',
        ])
        ->assertRedirect(route('gestion-academica.progresion'));

    $this->assertDatabaseHas('decisiones_progresion', [
        'gestion_id' => Gestion::activa()->id,
        'estudiante_id' => $student->id,
        'decision_recomendada' => 'avanza_con_arrastres',
        'decision_confirmada' => 'avanza_con_arrastres',
        'revisado_por' => $admin->id,
    ]);

    $this->actingAs($studentUser, 'student')
        ->get(route('estudiante.followup.index'))
        ->assertSeeText('Decisión administrativa final')
        ->assertSeeText('Avanzas con materias de arrastre');
});

test('administrators cannot confirm an enrollment before the teacher reports a result', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Redes Industriales');
    academicSchedule(academicTeacherProfile(), $subject);
    $enrollment = academicEnrollment($student, $subject);

    $this->actingAs($admin)
        ->patch(route('inscripciones.status', $enrollment), ['estado' => 'Aprobado'])
        ->assertSessionHasErrors([
            'estado' => 'El docente debe reportar el resultado antes de la revisión administrativa.',
        ]);

    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'estado' => 'Cursando',
        'revisado_por' => null,
    ]);
});

test('guests cannot view academic management', function () {
    $this->get(route('gestion-academica.index'))->assertRedirect(route('login'));
});

test('administrators cannot start another academic period while one is active', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Programación Industrial');
    $enrollment = academicEnrollment($student, $subject);

    $this->actingAs($admin)
        ->from(route('gestion-academica.index'))
        ->post(route('gestion-academica.iniciar'), [
            'anio' => 2027,
            'periodo' => '1',
        ])
        ->assertSessionHasErrors('periodo');

    $this->assertDatabaseHas('configuracion_academica', [
        'id' => 1,
        'gestion_vigente' => Gestion::activa()->codigo,
    ]);
    $this->assertDatabaseHas('inscripciones', [
        'id' => $enrollment->id,
        'gestion_id' => Gestion::activa()->id,
    ]);
});

test('teachers cannot start or close academic periods', function () {
    $teacher = academicTeacherActor(academicTeacherProfile());

    $this->actingAs($teacher)
        ->get(route('gestion-academica.index'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('panel-docente.estudiantes'))
        ->assertOk()
        ->assertSeeText('Gestión Académica')
        ->assertDontSee(route('gestion-academica.cerrar'), false);

    $this->actingAs($teacher)
        ->post(route('gestion-academica.iniciar'), [
            'anio' => 2027,
            'periodo' => '1',
        ])
        ->assertForbidden();
    $this->actingAs($teacher)
        ->post(route('gestion-academica.cerrar'))
        ->assertForbidden();

    $this->assertDatabaseHas('configuracion_academica', [
        'id' => 1,
        'gestion_vigente' => Gestion::activa()->codigo,
    ]);
});

test('teachers cannot start an academic period while the system is waiting', function () {
    $teacher = academicTeacherActor(academicTeacherProfile());
    Gestion::activa()->update(['activa' => false]);

    $this->actingAs($teacher)
        ->get(route('gestion-academica.index'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('panel-docente.estudiantes'))
        ->assertOk()
        ->assertSeeText('Sistema en espera');
});

test('new enrollment forms default to the active academic period', function () {
    $admin = academicAdminActor();
    $gestion = Gestion::activa();

    $this->actingAs($admin)
        ->get(route('inscripciones.create'))
        ->assertOk()
        ->assertSee('value="'.$gestion->codigo.'"', false);
});

test('administrators can browse current enrollments with their academic statuses', function () {
    $admin = academicAdminActor();
    $currentStudent = academicStudentProfile();
    $currentStudent->update(['nombres' => 'Vigente']);
    $currentSubject = academicSubject('Materia de gestión vigente');
    $currentEnrollment = academicEnrollment($currentStudent, $currentSubject);
    $approvedStudent = academicStudentProfile();
    $approvedStudent->update(['nombres' => 'Aprobado']);
    $approvedSubject = academicSubject('Materia aprobada');
    $approvedEnrollment = academicEnrollment($approvedStudent, $approvedSubject);
    $approvedEnrollment->update([
        'estado' => 'Aprobado',
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
        'resultado_final' => 'Aprobado',
        'revisado_por' => $admin->id,
        'fecha_revision' => now(),
    ]);
    $rejectedStudent = academicStudentProfile();
    $rejectedStudent->update(['nombres' => 'Reprobado']);
    $rejectedSubject = academicSubject('Materia reprobada');
    $rejectedEnrollment = academicEnrollment($rejectedStudent, $rejectedSubject);
    $rejectedEnrollment->update([
        'estado' => 'Reprobado',
        'resultado_reportado' => 'Reprobado',
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
        'resultado_final' => 'Reprobado',
        'revisado_por' => $admin->id,
        'fecha_revision' => now(),
    ]);
    $previousPeriodStudent = academicStudentProfile();
    $previousPeriodStudent->update(['nombres' => 'Anterior']);
    $previousPeriodSubject = academicSubject('Materia de gestión anterior');
    $previousPeriodEnrollment = academicEnrollment($previousPeriodStudent, $previousPeriodSubject);
    $previousPeriodEnrollment->update([
        'gestion_id' => Gestion::query()->where('codigo', 'I-2026')->value('id'),
    ]);

    $this->actingAs($admin)
        ->get(route('gestion-academica.index'))
        ->assertSee(route('inscripciones.index'), false);

    $this->actingAs($admin)
        ->get(route('inscripciones.index'))
        ->assertSeeText('Volver a Gestión académica')
        ->assertSeeText('Vigente')
        ->assertSeeText('Aprobado')
        ->assertSeeText('Reprobado')
        ->assertSeeText('Cursando')
        ->assertSeeText(academicGestionCodigo())
        ->assertDontSeeText('Anterior')
        ->assertDontSeeText('Inhabilitar')
        ->assertDontSeeText('Habilitar');
});

test('administrators can filter current enrollments by semester and subject together', function () {
    $admin = academicAdminActor();
    $firstSemesterStudent = academicStudentProfile();
    $firstSemesterStudent->update(['nombres' => 'Estudiante Primero']);
    $firstSemesterSubject = academicSubject('Materia de Primero');
    $secondSemesterStudent = academicStudentProfile();
    $secondSemesterStudent->update([
        'nombres' => 'Estudiante Segundo',
        'semestre' => 'Segundo',
    ]);
    $secondSemesterSubject = academicSubject('Materia de Segundo');
    $secondSemesterSubject->update(['semestre' => 'Segundo']);
    academicEnrollment($firstSemesterStudent, $firstSemesterSubject);
    academicEnrollment($secondSemesterStudent, $secondSemesterSubject);

    $this->actingAs($admin)
        ->get(route('inscripciones.index', ['semestre' => 'Segundo']))
        ->assertSeeText('Estudiante Segundo')
        ->assertSeeText('Materia de Segundo')
        ->assertDontSeeText('Estudiante Primero')
        ->assertSee('materia.semestre.toLowerCase() === this.semestre.toLowerCase()', false)
        ->assertSee('x-for="materia in materiasFiltradas"', false)
        ->assertSee('limpiarMateriaSiNoCorresponde()', false);

    $this->actingAs($admin)
        ->get(route('inscripciones.index', [
            'semestre' => 'Segundo',
            'materia_id' => $secondSemesterSubject->id,
        ]))
        ->assertSeeText('Estudiante Segundo')
        ->assertSeeText('Materia de Segundo')
        ->assertDontSeeText('Estudiante Primero');
});

test('the enrollment list rejects a subject that does not belong to the selected semester', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $firstSemesterSubject = academicSubject('Materia de Primero');
    academicEnrollment($student, $firstSemesterSubject);

    $this->actingAs($admin)
        ->get(route('inscripciones.index', [
            'semestre' => 'Segundo',
            'materia_id' => $firstSemesterSubject->id,
        ]))
        ->assertSessionHasErrors([
            'materia_id' => 'Selecciona una materia que pertenezca al semestre elegido.',
        ]);
});

test('the enrollment list rejects subject filters that are not in the active period', function () {
    $admin = academicAdminActor();
    $subjectWithoutCurrentEnrollments = academicSubject('Materia sin inscritos vigentes');

    $this->actingAs($admin)
        ->get(route('inscripciones.index', ['materia_id' => $subjectWithoutCurrentEnrollments->id]))
        ->assertSessionHasErrors('materia_id');
});

test('the enrollment list is empty while no academic period is active', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Materia archivada del periodo cerrado');
    academicEnrollment($student, $subject);
    Gestion::activa()->update(['activa' => false]);

    $this->actingAs($admin)
        ->get(route('inscripciones.index'))
        ->assertSeeText('No hay una gestión activa.')
        ->assertSeeText('No hay inscripciones para mostrar mientras el sistema está en espera.');
});

test('guests and teachers cannot browse the administrator enrollment list', function () {
    $this->get(route('inscripciones.index'))->assertRedirect(route('login'));

    $teacher = academicTeacherActor(academicTeacherProfile());

    $this->actingAs($teacher)
        ->get(route('inscripciones.index'))
        ->assertForbidden();
});

test('enrollment forms only list students from the selected semester', function () {
    $admin = academicAdminActor();
    $firstSemesterStudent = academicStudentProfile();
    $secondSemesterStudent = Estudiante::create([
        'nombres' => 'Segundo',
        'apellido_paterno' => 'Semestre',
        'apellido_materno' => 'Prueba',
        'matricula' => 'MAT-2002',
        'semestre' => 'Segundo',
        'generacion_ingreso' => 'II-2026',
        'ci' => '99001123',
        'estado' => 'activo',
    ]);
    $subject = academicSubject('Materia de Primero');

    $this->actingAs($admin)
        ->get(route('inscripciones.create', [
            'semestre' => 'Primero',
            'materia_id' => $subject->id,
        ]))
        ->assertOk()
        ->assertSeeText($firstSemesterStudent->nombres)
        ->assertDontSeeText($secondSemesterStudent->nombres);
});

test('administrators cannot start an invalid academic period', function () {
    $admin = academicAdminActor();

    $this->actingAs($admin)
        ->from(route('gestion-academica.index'))
        ->post(route('gestion-academica.iniciar'), [
            'anio' => 2027,
            'periodo' => '3',
        ])
        ->assertSessionHasErrors('periodo');

    $this->assertDatabaseHas('configuracion_academica', [
        'id' => 1,
        'gestion_vigente' => Gestion::activa()->codigo,
    ]);
});

test('administrators cannot start an academic period outside the supported year range', function () {
    $admin = academicAdminActor();

    $this->actingAs($admin)
        ->from(route('gestion-academica.index'))
        ->post(route('gestion-academica.iniciar'), [
            'anio' => 2051,
            'periodo' => '1',
        ])
        ->assertSessionHasErrors('anio');

    $this->assertDatabaseMissing('gestiones', ['codigo' => 'I-2051']);
});

test('administrators can start the second academic period by selecting its number and year', function () {
    $admin = academicAdminActor();
    Gestion::activa()->update(['activa' => false]);

    $this->actingAs($admin)
        ->post(route('gestion-academica.iniciar'), [
            'anio' => 2027,
            'periodo' => '2',
        ])
        ->assertRedirect(route('gestion-academica.index'))
        ->assertSessionHas('success');

    expect(ConfiguracionAcademica::gestionVigente())->toBe('II-2027');
    $this->assertDatabaseHas('gestiones', [
        'codigo' => 'II-2027',
        'anio' => 2027,
        'periodo' => '2',
        'fecha_inicio' => '2027-08-01',
        'fecha_fin' => '2027-11-30',
        'activa' => true,
    ]);
});

test('the academic landing page displays the four laboratory semesters', function () {
    $admin = academicAdminActor();

    $this->actingAs($admin)
        ->get(route('gestion-academica.index', ['gestion' => '2026-I']))
        ->assertOk()
        ->assertSeeText('Primero semestre')
        ->assertSeeText('Segundo semestre')
        ->assertSeeText('Tercero semestre')
        ->assertSeeText('Cuarto semestre')
        ->assertSeeText('Estado académico')
        ->assertSee(route('dashboard'), false)
        ->assertDontSee('name="gestion"', false)
        ->assertSeeText('Consultar inscripciones')
        ->assertSee(route('inscripciones.index'), false);
});

test('administrators can close a reviewed period, archive results, and manually start the next period', function () {
    $admin = academicAdminActor();
    $student = academicStudentProfile();
    $subject = academicSubject('Materia revisada');
    $enrollment = academicEnrollment($student, $subject);
    $gestion = Gestion::activa();
    $enrollment->update([
        'estado' => 'Aprobado',
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
        'resultado_final' => 'Aprobado',
        'revisado_por' => $admin->id,
        'fecha_revision' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('gestion-academica.progresion.confirmar', $student), [
            'decision' => 'avanza',
        ])
        ->assertRedirect(route('gestion-academica.progresion'));

    $this->actingAs($admin)
        ->post(route('gestion-academica.cerrar'))
        ->assertRedirect(route('gestion-academica.index'));

    expect($student->fresh()->semestre)->toBe('Segundo');
    expect(ConfiguracionAcademica::gestionVigente())->toBeNull();
    $this->assertDatabaseHas('configuracion_academica', [
        'id' => 1,
        'gestion_vigente' => $gestion->codigo,
    ]);
    $this->assertDatabaseHas('gestiones', [
        'id' => $gestion->id,
        'activa' => false,
    ]);
    $this->actingAs($admin)
        ->get(route('gestion-academica.index'))
        ->assertOk()
        ->assertSee(route('gestion-academica.iniciar'), false)
        ->assertSee('name="periodo"', false)
        ->assertSee('name="anio"', false)
        ->assertDontSee('name="gestion"', false);
    $this->assertDatabaseMissing('inscripciones', ['id' => $enrollment->id]);
    $this->assertDatabaseHas('inscripciones_archivadas', [
        'gestion' => $gestion->codigo,
        'inscripcion_original_id' => $enrollment->id,
        'decision_progresion' => 'avanza',
    ]);

    $this->actingAs($admin)
        ->post(route('gestion-academica.iniciar'), [
            'anio' => 2027,
            'periodo' => '1',
        ])
        ->assertRedirect(route('gestion-academica.index'))
        ->assertSessionHas('success');

    expect(ConfiguracionAcademica::gestionVigente())->toBe('I-2027');
    $this->assertDatabaseCount('inscripciones', 0);
});

function academicAdminActor(): User
{
    return User::create([
        'name' => 'Administrador académico',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'administrador',
        'docente_id' => academicTeacherProfile()->id,
    ]);
}

function academicGestionCodigo(): string
{
    return Gestion::activa()?->codigo
        ?? throw new RuntimeException('The test database must seed an active academic period.');
}

function academicTeacherActor(Docente $docente): User
{
    return User::create([
        'name' => 'Docente académico',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'docente',
        'docente_id' => $docente->id,
    ]);
}

function academicTeacherProfile(): Docente
{
    return Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Académico',
        'apellido_materno' => fake()->lastName(),
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
}

function academicStudentProfile(): Estudiante
{
    return Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Académico',
        'apellido_materno' => fake()->lastName(),
        'matricula' => fake()->unique()->numerify('MAT#####'),
        'semestre' => 'Primero',
        'ci' => fake()->unique()->numerify('########'),
        'estado' => 'activo',
    ]);
}

function academicSubject(string $name): Materia
{
    return Materia::create([
        'nombre' => $name,
        'semestre' => 'Primero',
        'estado' => 'activo',
    ]);
}

function academicSchedule(Docente $docente, Materia $materia): Horario
{
    $gestion = Gestion::activa();

    return Horario::create([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'docente_id' => $docente->id,
        'materia_id' => $materia->id,
        'dia_semana' => 'Lunes',
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => 'activo',
    ]);
}

function academicEnrollment(Estudiante $estudiante, Materia $materia): Inscripcion
{
    $gestion = Gestion::activa();

    return Inscripcion::create([
        'estudiante_id' => $estudiante->id,
        'materia_id' => $materia->id,
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'tipo' => 'Normal',
        'estado' => 'Cursando',
        'fecha_inscripcion' => '2026-10-14',
    ]);
}
