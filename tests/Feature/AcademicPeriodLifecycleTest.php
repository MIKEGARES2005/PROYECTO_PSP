<?php

use App\Models\ConfiguracionAcademica;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Horario;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('the 2026 academic periods exist with only the second period active', function () {
    $this->assertDatabaseHas('gestiones', [
        'codigo' => 'I-2026',
        'anio' => 2026,
        'periodo' => '1',
        'fecha_inicio' => '2026-02-01',
        'fecha_fin' => '2026-07-31',
        'activa' => false,
    ]);
    $this->assertDatabaseHas('gestiones', [
        'codigo' => 'II-2026',
        'anio' => 2026,
        'periodo' => '2',
        'fecha_inicio' => '2026-08-01',
        'fecha_fin' => '2026-11-30',
        'activa' => true,
    ]);

    expect(Gestion::query()->where('activa', true)->count())->toBe(1)
        ->and(ConfiguracionAcademica::gestionVigente())->toBe('II-2026');
});

test('a period cannot close while an enrollment has no final decision', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent();
    $materia = lifecycleSubject('Materia pendiente');
    $inscripcion = lifecycleEnrollment($student, $materia);
    $gestion = Gestion::activa();

    $this->actingAs($admin)
        ->from(route('gestion-academica.index'))
        ->followingRedirects()
        ->post(route('gestion-academica.cerrar'))
        ->assertOk()
        ->assertSeeText('La gestión no está concluida: faltan revisar 1 inscripción(es)')
        ->assertSeeText('Falta revisar 1 inscripción(es)')
        ->assertSeeText('Pendientes: Primero.');

    $this->assertDatabaseHas('gestiones', ['id' => $gestion->id, 'activa' => true]);
    $this->assertDatabaseHas('inscripciones', ['id' => $inscripcion->id, 'estado' => 'Cursando']);
    $this->assertDatabaseCount('inscripciones_archivadas', 0);
    expect(ConfiguracionAcademica::gestionVigente())->toBe($gestion->codigo);
});

test('closing archives enrollment decisions and progression before administration starts the next period', function () {
    $admin = lifecycleAdmin();
    $aprobado = lifecycleStudent();
    $conArrastre = lifecycleStudent();
    $repite = lifecycleStudent();
    $egresado = lifecycleStudent('Cuarto');
    $egresadoUser = User::factory()->estudiante($egresado)->create();
    $cuartoIncompleto = lifecycleStudent('Cuarto');
    $cuartoReprobado = lifecycleStudent('Cuarto');
    $gestion = Gestion::activa();

    $aprobadoMateria = lifecycleSubject('Aprobada');
    lifecycleFinalize(lifecycleEnrollment($aprobado, $aprobadoMateria), 'Aprobado', $admin);

    $arrastreMateria = lifecycleSubject('Arrastre');
    $segundoArrastreMateria = lifecycleSubject('Segundo arrastre');
    $aprobadaConArrastre = lifecycleSubject('Aprobada con arrastre');
    lifecycleFinalize(lifecycleEnrollment($conArrastre, $arrastreMateria), 'Reprobado', $admin);
    lifecycleFinalize(lifecycleEnrollment($conArrastre, $segundoArrastreMateria), 'Reprobado', $admin);
    lifecycleFinalize(lifecycleEnrollment($conArrastre, $aprobadaConArrastre), 'Aprobado', $admin);

    foreach (['Reprobada 1', 'Reprobada 2', 'Reprobada 3'] as $nombreMateria) {
        lifecycleFinalize(
            lifecycleEnrollment($repite, lifecycleSubject($nombreMateria)),
            'Reprobado',
            $admin,
        );
    }

    $cuartoMateria = lifecycleSubject('Cuarto aprobado', 'Cuarto');
    $segundaMateriaCuarto = lifecycleSubject('Otra materia de cuarto', 'Cuarto');
    lifecycleFinalize(lifecycleEnrollment($egresado, $cuartoMateria), 'Aprobado', $admin);
    lifecycleFinalize(lifecycleEnrollment($egresado, $segundaMateriaCuarto), 'Aprobado', $admin);
    lifecycleFinalize(lifecycleEnrollment($cuartoIncompleto, $cuartoMateria), 'Aprobado', $admin);
    lifecycleFinalize(lifecycleEnrollment($cuartoReprobado, $cuartoMateria), 'Reprobado', $admin);
    lifecycleFinalize(lifecycleEnrollment($cuartoReprobado, $segundaMateriaCuarto), 'Aprobado', $admin);

    foreach ([
        [$aprobado, 'avanza'],
        [$conArrastre, 'avanza_con_arrastres'],
        [$repite, 'repite_semestre'],
        [$egresado, 'retiro'],
        [$cuartoIncompleto, 'pendiente_materias_cuarto'],
        [$cuartoReprobado, 'repite_semestre'],
    ] as [$estudiante, $decision]) {
        $this->actingAs($admin)
            ->post(route('gestion-academica.progresion.confirmar', $estudiante), [
                'decision' => $decision,
            ])
            ->assertRedirect(route('gestion-academica.progresion'));
    }

    $this->actingAs($admin)
        ->post(route('gestion-academica.cerrar'))
        ->assertRedirect(route('gestion-academica.index'))
        ->assertSessionHas('success');

    expect($aprobado->fresh()->semestre)->toBe('Segundo')
        ->and($conArrastre->fresh()->semestre)->toBe('Segundo')
        ->and($repite->fresh()->semestre)->toBe('Primero')
        ->and($cuartoIncompleto->fresh()->semestre)->toBe('Cuarto')
        ->and($cuartoIncompleto->fresh()->estado)->toBe('activo')
        ->and($cuartoReprobado->fresh()->semestre)->toBe('Cuarto')
        ->and($cuartoReprobado->fresh()->estado)->toBe('activo');

    $this->assertDatabaseHas('estudiantes', [
        'id' => $egresado->id,
        'semestre' => 'Cuarto',
        'estado' => 'egresado',
        'deleted_at' => null,
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $egresadoUser->id,
        'activo' => false,
        'deleted_at' => null,
    ]);
    $this->assertDatabaseHas('historial_seguimiento_estudiantes', [
        'estudiante_id' => $egresado->id,
        'gestion_id' => $gestion->id,
        'tipo_evento' => 'egreso',
        'registrado_por' => $admin->id,
    ]);

    $this->assertDatabaseHas('inscripciones_archivadas', [
        'gestion' => $gestion->codigo,
        'estudiante_id' => $aprobado->id,
        'decision_progresion' => 'avanza',
    ]);
    $this->assertDatabaseHas('inscripciones_archivadas', [
        'gestion' => $gestion->codigo,
        'estudiante_id' => $conArrastre->id,
        'materia_id' => $aprobadaConArrastre->id,
        'decision_progresion' => 'avanza_con_arrastres',
    ]);
    $this->assertDatabaseHas('inscripciones_archivadas', [
        'gestion' => $gestion->codigo,
        'estudiante_id' => $egresado->id,
        'decision_progresion' => 'retiro',
    ]);
    $this->assertDatabaseHas('inscripciones_archivadas', [
        'gestion' => $gestion->codigo,
        'estudiante_id' => $cuartoIncompleto->id,
        'decision_progresion' => 'pendiente_materias_cuarto',
    ]);
    $this->assertDatabaseHas('inscripciones_archivadas', [
        'gestion' => $gestion->codigo,
        'estudiante_id' => $cuartoReprobado->id,
        'decision_progresion' => 'repite_semestre',
    ]);
    $this->assertDatabaseHas('inscripciones_archivadas', [
        'gestion' => $gestion->codigo,
        'estudiante_id' => $conArrastre->id,
        'materia_id' => $arrastreMateria->id,
        'decision_progresion' => 'arrastre',
    ]);
    $this->assertDatabaseCount('inscripciones_archivadas', 12);
    $this->assertDatabaseHas('inscripciones_archivadas', [
        'gestion' => $gestion->codigo,
        'estudiante_id' => $conArrastre->id,
        'materia_id' => $segundoArrastreMateria->id,
        'decision_progresion' => 'arrastre',
    ]);
    $this->assertDatabaseHas('inscripciones_archivadas', [
        'gestion' => $gestion->codigo,
        'estudiante_id' => $repite->id,
        'decision_progresion' => 'repite_semestre',
    ]);
    $this->assertDatabaseCount('inscripciones', 0);
    $this->assertDatabaseHas('gestiones', ['id' => $gestion->id, 'activa' => false]);

    $this->actingAs($admin)
        ->from(route('gestion-academica.index'))
        ->post(route('gestion-academica.iniciar'), [
            'anio' => $gestion->anio,
            'periodo' => $gestion->periodo,
        ])
        ->assertSessionHasErrors('periodo');

    $this->actingAs($admin)
        ->post(route('gestion-academica.iniciar'), [
            'anio' => 2027,
            'periodo' => '1',
        ])
        ->assertRedirect(route('gestion-academica.index'))
        ->assertSessionHas('success');

    expect(ConfiguracionAcademica::gestionVigente())->toBe('I-2027');
    $this->assertDatabaseHas('gestiones', [
        'codigo' => 'I-2027',
        'activa' => true,
        'fecha_inicio' => '2027-02-01',
        'fecha_fin' => '2027-07-31',
    ]);
    $this->assertDatabaseCount('inscripciones', 0);
});

test('progression decisions require reviewed results and a reason when administration changes the recommendation', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent();
    $materia = lifecycleSubject('Materia para revisar');
    $inscripcion = lifecycleEnrollment($student, $materia);

    $this->actingAs($admin)
        ->post(route('gestion-academica.progresion.confirmar', $student), [
            'decision' => 'repite_semestre',
        ])
        ->assertSessionHasErrors('decision');

    lifecycleFinalize($inscripcion, 'Aprobado', $admin);

    $this->actingAs($admin)
        ->get(route('gestion-academica.progresion'))
        ->assertSeeText('Progresión estudiantil')
        ->assertSeeText('Recomendación del sistema: Avanza al siguiente semestre')
        ->assertSeeText('Confirma o ajusta la recomendación');

    $this->actingAs($admin)
        ->post(route('gestion-academica.progresion.confirmar', $student), [
            'decision' => 'repite_semestre',
        ])
        ->assertSessionHasErrors([
            'motivo' => 'Explica el motivo para cambiar la recomendación (mínimo 10 caracteres).',
        ]);

    $this->assertDatabaseMissing('decisiones_progresion', [
        'gestion_id' => Gestion::activa()->id,
        'estudiante_id' => $student->id,
    ]);

    $this->actingAs($admin)
        ->post(route('gestion-academica.progresion.confirmar', $student), [
            'decision' => 'repite_semestre',
            'motivo' => 'Repite por decisión administrativa.',
        ])
        ->assertRedirect(route('gestion-academica.progresion'));

    $this->assertDatabaseHas('decisiones_progresion', [
        'gestion_id' => Gestion::activa()->id,
        'estudiante_id' => $student->id,
        'decision_recomendada' => 'avanza',
        'decision_confirmada' => 'repite_semestre',
        'motivo' => 'Repite por decisión administrativa.',
        'revisado_por' => $admin->id,
    ]);
});

test('closing a period requires every progression decision to be confirmed', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent();
    $inscripcion = lifecycleEnrollment($student, lifecycleSubject('Materia aprobada'));
    lifecycleFinalize($inscripcion, 'Aprobado', $admin);

    $this->actingAs($admin)
        ->post(route('gestion-academica.cerrar'))
        ->assertSessionHasErrors('cierre');

    $this->assertDatabaseHas('gestiones', [
        'id' => Gestion::activa()->id,
        'activa' => true,
    ]);
    $this->assertDatabaseCount('inscripciones_archivadas', 0);
});

test('only administrators can review progression or generate enrollments', function () {
    $teacherProfile = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Sin permisos',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    $teacher = User::create([
        'name' => 'Docente sin permisos',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'docente',
        'docente_id' => $teacherProfile->id,
    ]);

    $this->actingAs($teacher)
        ->get(route('gestion-academica.progresion'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->post(route('gestion-academica.inscripciones.generar'))
        ->assertForbidden();

    $this->assertDatabaseCount('inscripciones', 0);
});

test('administrators generate idempotent enrollments only after all required subjects have active schedules', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent();
    $firstSubject = lifecycleSubject('Primera materia');
    $secondSubject = lifecycleSubject('Segunda materia');
    $teacher = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Generación',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    $gestion = Gestion::activa();

    Horario::create([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'docente_id' => $teacher->id,
        'materia_id' => $firstSubject->id,
        'dia_semana' => 'Lunes',
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->from(route('gestion-academica.index'))
        ->post(route('gestion-academica.inscripciones.generar'))
        ->assertSessionHasErrors([
            'generacion' => 'Programa un horario activo en la gestión vigente para estas materias antes de generar inscripciones: Segunda materia.',
        ]);

    $this->assertDatabaseCount('inscripciones', 0);

    Horario::create([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'docente_id' => $teacher->id,
        'materia_id' => $secondSubject->id,
        'dia_semana' => 'Martes',
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->post(route('gestion-academica.inscripciones.generar'))
        ->assertSessionHas('success', 'Se generaron 2 inscripciones: 2 normales y 0 arrastres. Se omitieron 0 que ya existían.');

    $this->assertDatabaseCount('inscripciones', 2);
    $this->assertDatabaseHas('inscripciones', [
        'estudiante_id' => $student->id,
        'materia_id' => $firstSubject->id,
        'tipo' => 'Normal',
        'gestion_id' => $gestion->id,
    ]);
    $this->assertDatabaseHas('inscripciones', [
        'estudiante_id' => $student->id,
        'materia_id' => $secondSubject->id,
        'tipo' => 'Normal',
        'gestion_id' => $gestion->id,
    ]);

    $this->actingAs($admin)
        ->followingRedirects()
        ->post(route('gestion-academica.inscripciones.generar'))
        ->assertSee('$store.toasts.pushMany(', false)
        ->assertSee('No se crearon inscripciones nuevas: las materias requeridas ya estaban inscritas.', false)
        ->assertDontSeeText('No se crearon inscripciones nuevas: las materias requeridas ya estaban inscritas.')
        ->assertSeeText('Todos los estudiantes ya tienen sus materias requeridas inscritas. No hay inscripciones nuevas para generar.')
        ->assertDontSeeText($student->nombreCompleto())
        ->assertSee('disabled', false);

    $this->assertDatabaseCount('inscripciones', 2);
});

test('enrollment preview lists only missing subjects for partially enrolled students', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent();
    $registeredSubject = lifecycleSubject('Materia ya inscrita');
    $pendingSubject = lifecycleSubject('Materia pendiente de inscripción');
    $teacher = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Vista previa',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);

    foreach ([$registeredSubject, $pendingSubject] as $index => $materia) {
        Horario::create([
            'gestion_id' => Gestion::activa()->id,
            'gestion' => Gestion::activa()->codigo,
            'docente_id' => $teacher->id,
            'materia_id' => $materia->id,
            'dia_semana' => $index === 0 ? 'Lunes' : 'Martes',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
            'estado' => 'activo',
        ]);
    }

    lifecycleEnrollment($student, $registeredSubject);

    $this->actingAs($admin)
        ->get(route('gestion-academica.index'))
        ->assertSeeText($student->nombreCompleto())
        ->assertSeeText('Materia pendiente de inscripción')
        ->assertDontSeeText('Materia ya inscrita')
        ->assertSeeText('Continuar y generar');

    $this->actingAs($admin)
        ->post(route('gestion-academica.inscripciones.generar'))
        ->assertSessionHas('success', 'Se generaron 1 inscripciones: 1 normales y 0 arrastres. Se omitieron 1 que ya existían.');

    $this->assertDatabaseCount('inscripciones', 2);
});

test('next-period generation includes outstanding carry-over subjects alongside the current semester', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent('Segundo');
    $materiaArrastrada = lifecycleSubject('Materia pendiente de primero');
    $materiaActual = lifecycleSubject('Materia actual de segundo', 'Segundo');
    $gestionAnterior = Gestion::activa();
    DB::table('inscripciones_archivadas')->insert([
        'gestion' => $gestionAnterior->codigo,
        'inscripcion_original_id' => 999,
        'estudiante_id' => $student->id,
        'estudiante_nombre' => $student->nombreCompleto(),
        'matricula' => $student->matricula,
        'materia_id' => $materiaArrastrada->id,
        'materia_nombre' => $materiaArrastrada->nombre,
        'semestre' => 'Primero',
        'tipo' => 'Normal',
        'resultado_final' => 'Reprobado',
        'nota_final' => 40,
        'decision_progresion' => 'arrastre',
        'datos_inscripcion' => json_encode([], JSON_THROW_ON_ERROR),
        'archivado_en' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $gestionAnterior->update(['activa' => false]);
    $gestion = Gestion::query()->create([
        'codigo' => 'I-2027',
        'anio' => 2027,
        'periodo' => '1',
        'fecha_inicio' => '2027-02-01',
        'fecha_fin' => '2027-07-31',
        'activa' => true,
    ]);
    ConfiguracionAcademica::actualizarGestionVigente($gestion->codigo);
    $teacher = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Arrastre',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);

    foreach ([$materiaArrastrada, $materiaActual] as $index => $materia) {
        Horario::create([
            'gestion_id' => $gestion->id,
            'gestion' => $gestion->codigo,
            'docente_id' => $teacher->id,
            'materia_id' => $materia->id,
            'dia_semana' => $index === 0 ? 'Lunes' : 'Martes',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
            'estado' => 'activo',
        ]);
    }

    $this->actingAs($admin)
        ->get(route('gestion-academica.index'))
        ->assertSeeText('Inscripciones por semestre')
        ->assertSeeText('Segundo semestre')
        ->assertSeeText($student->nombreCompleto())
        ->assertSeeText('Materia pendiente de primero')
        ->assertSeeText('Materia actual de segundo')
        ->assertSeeText('Arrastre')
        ->assertSeeText('Continuar y generar')
        ->assertSee('aria-label="Filtrar por semestre"', false)
        ->assertSee(':aria-pressed="semestreInscripciones ===', false)
        ->assertSee('role="region"', false)
        ->assertSee('x-teleport="body"', false)
        ->assertSee('x-effect="document.body.classList.toggle(\'overflow-hidden\', confirmarGeneracion)"', false)
        ->assertSee('fixed inset-0 z-[100]', false)
        ->assertSee('semestreInscripciones ===', false);

    $this->assertDatabaseCount('inscripciones', 0);

    $this->actingAs($admin)
        ->post(route('gestion-academica.inscripciones.generar'))
        ->assertSessionHas('success', 'Se generaron 2 inscripciones: 1 normales y 1 arrastres. Se omitieron 0 que ya existían.');

    $this->assertDatabaseHas('inscripciones', [
        'estudiante_id' => $student->id,
        'materia_id' => $materiaArrastrada->id,
        'tipo' => 'Arrastre',
        'gestion_id' => $gestion->id,
    ]);
    $this->assertDatabaseHas('inscripciones', [
        'estudiante_id' => $student->id,
        'materia_id' => $materiaActual->id,
        'tipo' => 'Normal',
        'gestion_id' => $gestion->id,
    ]);
});

test('generation re-enrolls a repeating student in every active subject of the same semester', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent();
    $materiaReprobada = lifecycleSubject('Materia repetida');
    $materiaPendiente = lifecycleSubject('Otra materia del semestre');
    $gestionAnterior = Gestion::activa();
    DB::table('inscripciones_archivadas')->insert([
        'gestion' => $gestionAnterior->codigo,
        'inscripcion_original_id' => 1000,
        'estudiante_id' => $student->id,
        'estudiante_nombre' => $student->nombreCompleto(),
        'matricula' => $student->matricula,
        'materia_id' => $materiaReprobada->id,
        'materia_nombre' => $materiaReprobada->nombre,
        'semestre' => 'Primero',
        'tipo' => 'Normal',
        'resultado_final' => 'Reprobado',
        'nota_final' => 40,
        'decision_progresion' => 'repite_semestre',
        'datos_inscripcion' => json_encode([], JSON_THROW_ON_ERROR),
        'archivado_en' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $gestionAnterior->update(['activa' => false]);
    $gestion = Gestion::query()->create([
        'codigo' => 'I-2027',
        'anio' => 2027,
        'periodo' => '1',
        'fecha_inicio' => '2027-02-01',
        'fecha_fin' => '2027-07-31',
        'activa' => true,
    ]);
    ConfiguracionAcademica::actualizarGestionVigente($gestion->codigo);
    $teacher = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Repetición',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);

    foreach ([$materiaReprobada, $materiaPendiente] as $index => $materia) {
        Horario::create([
            'gestion_id' => $gestion->id,
            'gestion' => $gestion->codigo,
            'docente_id' => $teacher->id,
            'materia_id' => $materia->id,
            'dia_semana' => $index === 0 ? 'Lunes' : 'Martes',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
            'estado' => 'activo',
        ]);
    }

    $this->actingAs($admin)
        ->post(route('gestion-academica.inscripciones.generar'))
        ->assertSessionHas('success', 'Se generaron 2 inscripciones: 2 normales y 0 arrastres. Se omitieron 0 que ya existían.');

    $this->assertDatabaseHas('inscripciones', [
        'estudiante_id' => $student->id,
        'materia_id' => $materiaReprobada->id,
        'tipo' => 'Normal',
        'gestion_id' => $gestion->id,
    ]);
    $this->assertDatabaseHas('inscripciones', [
        'estudiante_id' => $student->id,
        'materia_id' => $materiaPendiente->id,
        'tipo' => 'Normal',
        'gestion_id' => $gestion->id,
    ]);
});

test('a student cannot be manually retired until every active fourth-semester subject is approved', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent('Cuarto');
    $materiaAprobada = lifecycleSubject('Cuarto aprobado', 'Cuarto');
    lifecycleSubject('Cuarto pendiente', 'Cuarto');
    lifecycleFinalize(lifecycleEnrollment($student, $materiaAprobada), 'Aprobado', $admin);

    $this->actingAs($admin)
        ->from(route('seguimiento-estudiantil.show', $student))
        ->post(route('seguimiento-estudiantil.events.store', $student), [
            'tipo_evento' => 'retiro',
            'descripcion' => 'Solicitud de retiro',
            'fecha_evento' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('tipo_evento');

    expect($student->fresh()->estado)->toBe('activo');
    $this->assertDatabaseMissing('historial_seguimiento_estudiantes', [
        'estudiante_id' => $student->id,
        'tipo_evento' => 'retiro',
    ]);
});

test('waiting periods disable academic writes and are visible to teachers and students', function () {
    $admin = lifecycleAdmin();
    $gestion = Gestion::activa();
    $teacherProfile = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Periodo Cerrado',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    $teacher = User::create([
        'name' => 'Docente en espera',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'docente',
        'docente_id' => $teacherProfile->id,
    ]);
    $materia = lifecycleSubject('Materia de periodo cerrado');
    Horario::create([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'docente_id' => $teacherProfile->id,
        'materia_id' => $materia->id,
        'dia_semana' => 'Lunes',
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)->post(route('gestion-academica.cerrar'));

    $student = lifecycleStudent();
    $studentUser = User::factory()->estudiante($student)->create();

    $this->actingAs($admin)
        ->get(route('gestion-academica.index'))
        ->assertOk()
        ->assertSeeText('Sistema en espera');

    $this->actingAs($admin)
        ->get(route('gestion-academica.semestre', 'Primero'))
        ->assertOk()
        ->assertDontSeeText('Docente Periodo Cerrado');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Sin gestión vigente');

    $this->actingAs($admin)
        ->from(route('gestion-academica.index'))
        ->get(route('horarios.create'))
        ->assertSessionHasErrors('periodo');

    $this->actingAs($admin)
        ->from(route('gestion-academica.index'))
        ->get(route('inscripciones.create'))
        ->assertSessionHasErrors('periodo');

    $this->actingAs($teacher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Sistema en espera');

    $this->actingAs($teacher)
        ->get(route('panel-docente.estudiantes'))
        ->assertOk()
        ->assertSeeText('No hay una gestión activa');

    $this->actingAs($studentUser, 'student')
        ->get(route('estudiante.dashboard'))
        ->assertOk()
        ->assertSeeText('El sistema está en espera');

    $this->actingAs($studentUser, 'student')
        ->get(route('estudiante.followup.index'))
        ->assertOk()
        ->assertSeeText('Sistema en espera');

    $this->actingAs($studentUser, 'student')
        ->get(route('estudiante.equipment.create'))
        ->assertRedirect(route('estudiante.dashboard'))
        ->assertSessionHasErrors('periodo');

    expect(ConfiguracionAcademica::gestionVigente())->toBeNull();
});

test('a student can reincorporate before new-period enrollment generation and receive normal enrollments', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent();
    $studentUser = User::factory()->estudiante($student)->create();
    $gestion = Gestion::activa();

    $this->actingAs($admin)
        ->post(route('seguimiento-estudiantil.events.store', $student), [
            'tipo_evento' => 'congelamiento',
            'descripcion' => 'Pausa antes de iniciar la nueva gestión.',
            'fecha_evento' => now()->toDateString(),
        ])
        ->assertRedirect(route('seguimiento-estudiantil.index'));

    $this->actingAs($admin)
        ->post(route('seguimiento-estudiantil.events.store', $student), [
            'tipo_evento' => 'reincorporacion',
            'fecha_evento' => now()->toDateString(),
        ])
        ->assertRedirect(route('seguimiento-estudiantil.index'));

    $subject = lifecycleSubject('Materia de reincorporación');
    $teacher = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Reincorporación',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    Horario::create([
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'docente_id' => $teacher->id,
        'materia_id' => $subject->id,
        'dia_semana' => 'Lunes',
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => 'activo',
    ]);

    $this->actingAs($admin)
        ->post(route('gestion-academica.inscripciones.generar'))
        ->assertSessionHas('success', 'Se generaron 1 inscripciones: 1 normales y 0 arrastres. Se omitieron 0 que ya existían.');

    $this->assertDatabaseHas('inscripciones', [
        'gestion_id' => $gestion->id,
        'estudiante_id' => $student->id,
        'materia_id' => $subject->id,
        'tipo' => 'Normal',
    ]);
    expect($student->fresh()->estado)->toBe('activo')
        ->and($studentUser->fresh()->activo)->toBeTrue();
});

test('a student cannot be frozen while they have current-period enrollments', function () {
    $admin = lifecycleAdmin();
    $student = lifecycleStudent();
    $inscripcion = lifecycleEnrollment($student, lifecycleSubject('Materia en curso'));

    $this->actingAs($admin)
        ->from(route('seguimiento-estudiantil.show', $student))
        ->post(route('seguimiento-estudiantil.events.store', $student), [
            'tipo_evento' => 'congelamiento',
            'descripcion' => 'Pausa solicitada durante la gestión.',
            'fecha_evento' => now()->toDateString(),
        ])
        ->assertSessionHasErrors([
            'tipo_evento' => 'No se puede congelar al estudiante mientras tenga inscripciones en la gestión vigente. Primero debe terminarse y cerrarse el periodo.',
        ]);

    expect($student->fresh()->estado)->toBe('activo');
    $this->assertDatabaseHas('inscripciones', [
        'id' => $inscripcion->id,
        'estado' => 'Cursando',
    ]);
    $this->assertDatabaseMissing('historial_seguimiento_estudiantes', [
        'estudiante_id' => $student->id,
        'tipo_evento' => 'congelamiento',
    ]);
});

test('a frozen student cannot reincorporate after enrollment generation has started', function () {
    $admin = lifecycleAdmin();
    $frozenStudent = lifecycleStudent();
    $activeStudent = lifecycleStudent();
    $this->actingAs($admin)
        ->post(route('seguimiento-estudiantil.events.store', $frozenStudent), [
            'tipo_evento' => 'congelamiento',
            'descripcion' => 'Pausa de estudios.',
            'fecha_evento' => now()->toDateString(),
        ])
        ->assertRedirect(route('seguimiento-estudiantil.index'));

    lifecycleEnrollment($activeStudent, lifecycleSubject('Inscripción ya generada'));

    $this->actingAs($admin)
        ->from(route('seguimiento-estudiantil.show', $frozenStudent))
        ->post(route('seguimiento-estudiantil.events.store', $frozenStudent), [
            'tipo_evento' => 'reincorporacion',
            'fecha_evento' => now()->toDateString(),
        ])
        ->assertSessionHasErrors([
            'tipo_evento' => 'La gestión ya tiene inscripciones generadas. La reincorporación deberá registrarse al inicio de la siguiente gestión.',
        ]);

    expect($frozenStudent->fresh()->estado)->toBe('congelado');
});

function lifecycleAdmin(): User
{
    return User::create([
        'name' => 'Administrador de ciclo',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'administrador',
    ]);
}

function lifecycleStudent(string $semester = 'Primero'): Estudiante
{
    return Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Ciclo',
        'matricula' => fake()->unique()->numerify('CICLO#####'),
        'semestre' => $semester,
        'ci' => fake()->unique()->numerify('########'),
        'estado' => 'activo',
    ]);
}

function lifecycleSubject(string $name, string $semester = 'Primero'): Materia
{
    return Materia::create([
        'nombre' => $name,
        'semestre' => $semester,
        'estado' => 'activo',
    ]);
}

function lifecycleEnrollment(Estudiante $student, Materia $materia): Inscripcion
{
    $gestion = Gestion::activa();

    return Inscripcion::create([
        'estudiante_id' => $student->id,
        'materia_id' => $materia->id,
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'tipo' => 'Normal',
        'estado' => 'Cursando',
        'fecha_inscripcion' => now()->toDateString(),
    ]);
}

function lifecycleFinalize(Inscripcion $inscripcion, string $resultado, User $admin): void
{
    $inscripcion->update([
        'resultado_reportado' => $resultado,
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
        'resultado_final' => $resultado,
        'revisado_por' => $admin->id,
        'fecha_revision' => now(),
        'estado' => $resultado,
    ]);
}
