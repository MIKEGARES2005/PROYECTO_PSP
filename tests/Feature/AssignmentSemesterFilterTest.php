<?php

use App\Models\Activo;
use App\Models\Categoria;
use App\Models\Docente;
use App\Models\Equipo;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function assignmentSemesterContext(): array
{
    $admin = User::create(['username' => 'admin-semestres', 'password' => 'password', 'tipo_usuario' => 'administrador']);
    $docente = Docente::create(['nombres' => 'Docente', 'apellido_paterno' => 'Semestre', 'ci' => '91000001', 'cargo' => 'Docente', 'estado' => 'activo']);
    $gestion = Gestion::activa();
    $materia = Materia::create(['nombre' => 'Materia de segundo', 'sigla' => 'MAT-SEG', 'semestre' => 'segundo', 'estado' => 'activo']);
    $horario = Horario::create(['gestion_id' => $gestion->id, 'docente_id' => $docente->id, 'materia_id' => $materia->id, 'dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'aula' => 'Aula semestre 2', 'estado' => 'activo']);
    $estudiante = Estudiante::create(['nombres' => 'Estudiante', 'apellido_paterno' => 'Segundo', 'ci' => '91000002', 'matricula' => 'MAT-SEG-01', 'semestre' => '2', 'estado' => 'activo']);
    DB::table('inscripciones')->insert(['estudiante_id' => $estudiante->id, 'materia_id' => $materia->id, 'gestion_id' => $gestion->id, 'gestion' => $gestion->codigo, 'tipo' => 'normal', 'estado' => 'Cursando', 'fecha_inscripcion' => '2026-08-01', 'created_at' => now(), 'updated_at' => now()]);
    $category = Categoria::create(['nombre' => 'Equipos semestre', 'grupo' => 'equipo', 'estado' => 'activa']);
    $asset = Activo::create(['codigo' => 'SEM-EQ-001', 'categoria_id' => $category->id, 'nombre' => 'Equipo semestre', 'estado_uso' => 'disponible']);
    $equipment = Equipo::create(['activo_id' => $asset->id, 'identificador' => 'SEM-LAB-001', 'tipo_equipo' => 'cpu', 'modo_uso' => 'individual']);

    return compact('admin', 'docente', 'gestion', 'horario', 'estudiante', 'equipment');
}

test('assignment form exposes the academic semester filter', function () {
    $context = assignmentSemesterContext();

    $this->actingAs($context['admin'])
        ->get(route('asignaciones.create'))
        ->assertOk()
        ->assertSee('id="semestre"', false)
        ->assertSeeText('Primero')
        ->assertSeeText('Segundo')
        ->assertSeeText('Tercero')
        ->assertSeeText('Cuarto')
        ->assertSeeText('Materia de segundo')
        ->assertSee('data-materia="'.$context['horario']->materia_id.'"', false)
        ->assertSee('data-semestre="Segundo"', false)
        ->assertSee('data-materias="'.$context['horario']->materia_id.'"', false)
        ->assertSeeText('Estudiante Segundo');
});

test('assignment form includes every active scheduled subject even when no student is currently enrolled in it', function () {
    $context = assignmentSemesterContext();
    $otherSubject = Materia::create([
        'nombre' => 'Otra materia de segundo',
        'sigla' => 'OTR-SEG',
        'semestre' => 'Segundo',
        'estado' => 'activo',
    ]);
    Horario::create([
        'gestion_id' => $context['gestion']->id,
        'docente_id' => $context['docente']->id,
        'materia_id' => $otherSubject->id,
        'dia_semana' => 2,
        'hora_inicio' => '10:00',
        'hora_fin' => '12:00',
        'aula' => 'Aula segunda materia',
        'estado' => 'activo',
    ]);

    $this->actingAs($context['admin'])
        ->get(route('asignaciones.create'))
        ->assertOk()
        ->assertSeeText('Materia de segundo')
        ->assertSeeText('Otra materia de segundo')
        ->assertSee('data-semestre="Segundo"', false);
});

test('assignment rejects a student from a different selected semester', function () {
    $context = assignmentSemesterContext();

    $this->actingAs($context['admin'])
        ->from(route('asignaciones.create'))
        ->post(route('asignaciones.store'), [
            'semestre' => 'Primero',
            'equipo_id' => $context['equipment']->id,
            'estudiante_id' => $context['estudiante']->id,
            'materia_id' => $context['horario']->materia_id,
            'horario_id' => $context['horario']->id,
            'modalidad' => 'individual',
            'fecha_entrega' => '2026-10-30 08:00:00',
            'fecha_devolucion_prevista' => '2026-10-30 12:00:00',
        ])
        ->assertRedirect(route('asignaciones.create'))
        ->assertSessionHasErrors('horario_id');
});

test('semester values are grouped regardless of case or numeric representation', function () {
    expect(Materia::nombreSemestre('PRIMERO'))->toBe('Primero')
        ->and(Materia::nombreSemestre(' segundo '))->toBe('Segundo')
        ->and(Materia::nombreSemestre('3'))->toBe('Tercero')
        ->and(Materia::nombreSemestre('Cuarto'))->toBe('Cuarto');
});

test('administrator sees schedules from every teacher even with an associated teacher profile', function () {
    $context = assignmentSemesterContext();
    $context['admin']->update(['docente_id' => $context['docente']->id]);

    $otherDocente = Docente::create(['nombres' => 'Otro', 'apellido_paterno' => 'Docente', 'ci' => '91000003', 'cargo' => 'Docente', 'estado' => 'activo']);
    $materia = Materia::create(['nombre' => 'Materia de tercero', 'sigla' => 'MAT-TER', 'semestre' => 'Tercero', 'estado' => 'activo']);
    $horario = Horario::create(['gestion_id' => $context['gestion']->id, 'docente_id' => $otherDocente->id, 'materia_id' => $materia->id, 'dia_semana' => 2, 'hora_inicio' => '10:00', 'hora_fin' => '12:00', 'aula' => 'Aula semestre 3', 'estado' => 'activo']);
    $estudiante = Estudiante::create(['nombres' => 'Estudiante', 'apellido_paterno' => 'Tercero', 'ci' => '91000004', 'matricula' => 'MAT-TER-01', 'semestre' => '3', 'estado' => 'activo']);
    DB::table('inscripciones')->insert(['estudiante_id' => $estudiante->id, 'materia_id' => $materia->id, 'gestion_id' => $context['gestion']->id, 'gestion' => $context['gestion']->codigo, 'tipo' => 'normal', 'estado' => 'Cursando', 'fecha_inscripcion' => '2026-08-01', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($context['admin'])
        ->get(route('asignaciones.create'))
        ->assertOk()
        ->assertSeeText('Materia de tercero')
        ->assertSee('data-materia="'.$horario->materia_id.'"', false)
        ->assertSeeText('Estudiante Tercero');
});
