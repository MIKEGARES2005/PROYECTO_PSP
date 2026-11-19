<?php

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Diagnostico;
use App\Models\Docente;
use App\Models\Equipo;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Heartbeat;
use App\Models\Horario;
use App\Models\Incidencia;
use App\Models\Materia;
use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function studentEquipmentContext(): array
{
    $student = Estudiante::create(['nombres' => 'Estudiante', 'apellido_paterno' => 'Prueba', 'ci' => '98000001', 'matricula' => 'MAT-PRUEBA', 'semestre' => '1', 'estado' => 'activo']);
    $user = User::factory()->estudiante($student)->create();
    $docente = Docente::create(['nombres' => 'Docente', 'apellido_paterno' => 'Prueba', 'ci' => '98000002', 'cargo' => 'Docente', 'estado' => 'activo']);
    $gestion = Gestion::activa();
    $materia = Materia::create(['nombre' => 'Laboratorio de prueba', 'sigla' => 'LAB-001', 'semestre' => 'Primero', 'estado' => 'activo']);
    $horario = Horario::create(['gestion_id' => $gestion->id, 'gestion' => $gestion->codigo, 'docente_id' => $docente->id, 'materia_id' => $materia->id, 'dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'aula' => 'A-1', 'estado' => 'activo']);
    DB::table('inscripciones')->insert(['estudiante_id' => $student->id, 'materia_id' => $materia->id, 'gestion_id' => $gestion->id, 'gestion' => $gestion->codigo, 'tipo' => 'normal', 'estado' => 'Cursando', 'fecha_inscripcion' => '2026-08-01', 'created_at' => now(), 'updated_at' => now()]);
    $category = Categoria::create(['nombre' => 'Equipos de prueba', 'grupo' => 'equipo', 'estado' => 'activa']);
    $asset = Activo::create(['codigo' => 'TEST-EQ-001', 'categoria_id' => $category->id, 'nombre' => 'Equipo de prueba', 'estado_uso' => 'disponible']);

    return compact('student', 'user', 'horario', 'asset');
}

test('student can open equipment request and academic follow-up', function () {
    $context = studentEquipmentContext();

    $this->actingAs($context['user'], 'student')->get(route('estudiante.equipment.create'))->assertOk()->assertSeeText('Laboratorio de prueba');
    $this->actingAs($context['user'], 'student')->get(route('estudiante.followup.index'))->assertOk()->assertSeeText('Laboratorio de prueba');
});

test('student equipment request remains pending until an approver acts', function () {
    $context = studentEquipmentContext();

    $this->actingAs($context['user'], 'student')
        ->post(route('estudiante.equipment.store'), ['horario_id' => $context['horario']->id, 'tipo_equipo' => 'cpu', 'fecha_uso' => now()->addDay()->toDateString()])
        ->assertRedirect(route('estudiante.loans.index'));

    $this->assertDatabaseHas('solicitudes', ['estudiante_solicitante_id' => $context['student']->id, 'horario_id' => $context['horario']->id, 'estado' => 'pendiente']);
});

test('administrator can filter equipment requests by the shared status buttons', function () {
    $context = studentEquipmentContext();
    $administrator = User::factory()->administrador()->create();

    foreach ([
        'pendiente',
        'aprobada',
        'parcialmente_aprobada',
        'rechazada',
        'cancelada',
    ] as $status) {
        Solicitud::create([
            'horario_id' => $context['horario']->id,
            'estudiante_solicitante_id' => $context['student']->id,
            'modalidad' => 'individual',
            'tipo_equipo' => 'cpu',
            'fecha_uso' => now()->toDateString(),
            'estado' => $status,
            'observaciones' => 'Solicitud estado '.$status,
        ]);
    }

    $this->actingAs($administrator)
        ->get(route('admin.solicitudes.index', ['panel' => 'equipos', 'estado' => 'pendiente']))
        ->assertSeeText('Solicitud estado pendiente')
        ->assertSeeText('Pendientes')
        ->assertSeeText('Aprobadas')
        ->assertSeeText('Rechazadas')
        ->assertSeeText('Canceladas')
        ->assertSeeText('Aprobar y asignar')
        ->assertDontSeeText('Solicitud estado aprobada')
        ->assertDontSeeText('Solicitud estado rechazada');

    $this->actingAs($administrator)
        ->get(route('admin.solicitudes.index', ['panel' => 'equipos', 'estado' => 'desconocido']))
        ->assertSeeText('Solicitud estado pendiente')
        ->assertDontSeeText('Solicitud estado aprobada');

    $this->actingAs($administrator)
        ->get(route('admin.solicitudes.index', ['panel' => 'equipos', 'estado' => 'aprobada']))
        ->assertSeeText('Solicitud estado aprobada')
        ->assertSeeText('Solicitud estado parcialmente_aprobada')
        ->assertSeeText('Parcialmente aprobada')
        ->assertDontSeeText('Aprobar y asignar')
        ->assertDontSeeText('Solicitud estado pendiente');

    foreach (['rechazada', 'cancelada'] as $status) {
        $this->actingAs($administrator)
            ->get(route('admin.solicitudes.index', ['panel' => 'equipos', 'estado' => $status]))
            ->assertSeeText('Solicitud estado '.$status)
            ->assertDontSeeText('Aprobar y asignar')
            ->assertDontSeeText('Solicitud estado pendiente');
    }
});

test('student dashboard shows app status and alerts only for their own assigned equipment', function () {
    $context = studentEquipmentContext();
    $otherStudent = Estudiante::create([
        'nombres' => 'Otro estudiante',
        'apellido_paterno' => 'Ajeno',
        'ci' => '98000003',
        'matricula' => 'MAT-AJENO',
        'semestre' => '1',
        'estado' => 'activo',
    ]);
    $administrator = User::factory()->administrador()->create();
    $equipment = Equipo::create([
        'activo_id' => $context['asset']->id,
        'identificador' => 'STUDENT-PC-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
        'token_hash' => hash('sha256', 'student-device-token'),
    ]);
    $assignment = Asignacion::create([
        'codigo' => 'STUDENT-ASG-001',
        'tipo' => 'asignado',
        'modalidad' => 'individual',
        'equipo_id' => $equipment->id,
        'estudiante_id' => $context['student']->id,
        'horario_id' => $context['horario']->id,
        'origen' => 'panel_web',
        'autorizado_por' => $administrator->id,
        'fecha_entrega' => now()->subHour(),
        'fecha_devolucion_prevista' => now()->addHour(),
        'estado' => 'activa',
    ]);
    Heartbeat::create([
        'equipo_id' => $equipment->id,
        'cpu_uso' => 42,
        'ram_uso' => 60,
        'fecha_hora' => now()->subMinute(),
    ]);
    $heartbeat = Heartbeat::query()->where('equipo_id', $equipment->id)->firstOrFail();
    Diagnostico::create([
        'equipo_id' => $equipment->id,
        'heartbeat_id' => $heartbeat->id,
        'tipo' => 'Rendimiento',
        'nivel' => 'critica',
        'codigo_regla' => 'CPU_TEST',
        'descripcion' => 'Revisión automática del equipo propio.',
        'fecha_hora' => now(),
    ]);
    Incidencia::create([
        'activo_id' => $context['asset']->id,
        'asignacion_id' => $assignment->id,
        'tipo' => 'Hardware',
        'descripcion' => 'Aviso de prueba del equipo propio.',
        'prioridad' => 'media',
        'estado' => 'abierta',
        'origen' => 'app',
        'reportado_por' => $administrator->id,
        'fecha_reporte' => now(),
    ]);
    $otherCategory = Categoria::create(['nombre' => 'Equipo ajeno', 'grupo' => 'equipo', 'estado' => 'activa']);
    $otherAsset = Activo::create([
        'codigo' => 'STUDENT-OTHER-ASSET',
        'categoria_id' => $otherCategory->id,
        'nombre' => 'Equipo ajeno',
        'estado_uso' => 'asignado',
    ]);
    $otherEquipment = Equipo::create([
        'activo_id' => $otherAsset->id,
        'identificador' => 'STUDENT-PC-OTHER',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    Asignacion::create([
        'codigo' => 'STUDENT-ASG-OTHER',
        'tipo' => 'asignado',
        'modalidad' => 'individual',
        'equipo_id' => $otherEquipment->id,
        'estudiante_id' => $otherStudent->id,
        'horario_id' => $context['horario']->id,
        'origen' => 'panel_web',
        'autorizado_por' => $administrator->id,
        'fecha_entrega' => now()->subHour(),
        'fecha_devolucion_prevista' => now()->addHour(),
        'estado' => 'activa',
    ]);
    Heartbeat::create([
        'equipo_id' => $otherEquipment->id,
        'cpu_uso' => 90,
        'fecha_hora' => now()->subMinute(),
    ]);

    $this->actingAs($context['user'], 'student')
        ->get(route('estudiante.dashboard'))
        ->assertOk()
        ->assertSeeText('Mis equipos y estado de la app')
        ->assertSeeText('TEST-EQ-001')
        ->assertSeeText('App conectada')
        ->assertSeeText('Incidencias pendientes')
        ->assertSeeText('Aviso de prueba del equipo propio.')
        ->assertSeeText('Revisión automática del equipo propio.')
        ->assertDontSeeText('STUDENT-OTHER-ASSET')
        ->assertDontSeeText('Equipo ajeno');
});

test('student return notification opens the admin approval page', function () {
    $context = studentEquipmentContext();
    $administrator = User::factory()->administrador()->create();
    $equipment = Equipo::create([
        'activo_id' => $context['asset']->id,
        'identificador' => 'STUDENT-RETURN-PC',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    $assignment = Asignacion::create([
        'codigo' => 'STUDENT-RETURN-ASG',
        'tipo' => 'asignado',
        'modalidad' => 'individual',
        'equipo_id' => $equipment->id,
        'estudiante_id' => $context['student']->id,
        'horario_id' => $context['horario']->id,
        'origen' => 'panel_web',
        'autorizado_por' => $administrator->id,
        'fecha_entrega' => now()->subHour(),
        'fecha_devolucion_prevista' => now()->addHour(),
        'estado' => 'activa',
    ]);

    $this->actingAs($context['user'], 'student')
        ->from(route('estudiante.loans.index'))
        ->post(route('estudiante.loans.return', $assignment), ['motivo_devolucion' => 'Fin de uso'])
        ->assertRedirect(route('estudiante.loans.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('asignaciones', [
        'id' => $assignment->id,
        'estado' => 'devolucion_pendiente',
        'motivo_devolucion' => 'Fin de uso',
    ]);
    $notification = Notificacion::query()
        ->where('user_id', $administrator->id)
        ->where('tipo', 'devolucion_equipo')
        ->firstOrFail();
    expect($notification->url)->toBe(route('solicitudes.equipos.index'));

    $this->actingAs($administrator)
        ->get($notification->url)
        ->assertOk()
        ->assertSeeText('Devoluciones pendientes')
        ->assertSeeText('Fin de uso');
});
