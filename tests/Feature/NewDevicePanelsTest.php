<?php

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Complemento;
use App\Models\Diagnostico;
use App\Models\Docente;
use App\Models\Equipo;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Heartbeat;
use App\Models\Horario;
use App\Models\Incidencia;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

test('administrators can register an incident against an app-monitored equipment asset', function () {
    $administrator = User::factory()->administrador()->create();
    $equipment = createDevicePanelEquipment('PANEL-INC-001', 'PC-PANEL-INC-001');

    $this->actingAs($administrator)
        ->get(route('incidencias.create'))
        ->assertOk()
        ->assertSeeText('PANEL-INC-001');

    $this->actingAs($administrator)
        ->post(route('incidencias.store'), [
            'equipo_id' => $equipment->id,
            'tipo' => 'Hardware',
            'descripcion' => 'El equipo presenta fallos de encendido.',
            'prioridad' => 'alta',
            'estado' => 'abierta',
            'fecha_reporte' => '2026-10-30T09:30',
        ])
        ->assertRedirect(route('incidencias.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('incidencias', [
        'activo_id' => $equipment->activo_id,
        'asignacion_id' => null,
        'prioridad' => 'alta',
        'estado' => 'abierta',
        'origen' => 'manual',
        'reportado_por' => $administrator->id,
    ]);
});

test('administrators can resolve an incident with a recorded resolver and timestamp', function () {
    $administrator = User::factory()->administrador()->create();
    $equipment = createDevicePanelEquipment('PANEL-RES-001', 'PC-PANEL-RES-001');
    $incident = Incidencia::create([
        'activo_id' => $equipment->activo_id,
        'tipo' => 'Hardware',
        'descripcion' => 'El ventilador hace ruido.',
        'prioridad' => 'media',
        'estado' => 'abierta',
        'origen' => 'manual',
        'reportado_por' => $administrator->id,
        'fecha_reporte' => now()->subHour(),
    ]);

    $this->actingAs($administrator)
        ->put(route('incidencias.resolver', $incident->id))
        ->assertRedirect(route('incidencias.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('incidencias', [
        'id' => $incident->id,
        'estado' => 'resuelta',
        'resuelto_por' => $administrator->id,
    ]);
    expect($incident->fresh()->fecha_resolucion)->not->toBeNull();
});

test('staff monitoring and diagnostic panels show device app data', function () {
    $administrator = User::factory()->administrador()->create();
    $equipment = createDevicePanelEquipment('PANEL-MON-001', 'PC-PANEL-MON-001');
    $heartbeat = Heartbeat::create([
        'equipo_id' => $equipment->id,
        'cpu_uso' => 42.5,
        'ram_uso' => 61.25,
        'fecha_hora' => now()->subMinute(),
        'metricas' => [
            'cliente' => 'agente_pc',
            'nombre' => 'Monitor de laboratorio',
        ],
    ]);
    Diagnostico::create([
        'equipo_id' => $equipment->id,
        'heartbeat_id' => $heartbeat->id,
        'tipo' => 'Rendimiento',
        'nivel' => 'critica',
        'codigo_regla' => 'CPU_ALTA',
        'descripcion' => 'El procesador supera el umbral configurado.',
        'fecha_hora' => now(),
    ]);

    $this->actingAs($administrator)
        ->get(route('monitoreo.index'))
        ->assertOk()
        ->assertSeeText('PANEL-MON-001')
        ->assertSeeText('Monitor de laboratorio')
        ->assertSeeText('Conectado');

    $this->actingAs($administrator)
        ->get(route('diagnosticos.index', ['nivel' => 'critica']))
        ->assertOk()
        ->assertSeeText('CPU_ALTA')
        ->assertSeeText('El procesador supera el umbral configurado.')
        ->assertSeeText('Monitor PC');
});

test('diagnostics review current reports from PC and tablet devices for administrators with a teacher profile', function () {
    $teacher = createDevicePanelTeacher('AdminDiagnostico');
    $administrator = $teacher['user'];
    $administrator->update(['tipo_usuario' => 'administrador']);
    $pc = createDevicePanelEquipment('PANEL-DIAG-PC', 'PC-PANEL-DIAG-PC');
    $tablet = createDevicePanelEquipment('PANEL-DIAG-TAB', 'TAB-PANEL-DIAG-TAB', 'tablet');

    Heartbeat::create([
        'equipo_id' => $pc->id,
        'cpu_uso' => 46,
        'ram_uso' => 62,
        'almacenamiento_uso' => 38,
        'fecha_hora' => now()->subMinute(),
        'metricas' => ['cliente' => 'agente_pc'],
    ]);
    Heartbeat::create([
        'equipo_id' => $tablet->id,
        'ram_uso' => 74,
        'almacenamiento_uso' => 35,
        'bateria' => 60,
        'temperatura' => 28.5,
        'cargando' => true,
        'fecha_hora' => now()->subMinute(),
        'metricas' => ['cliente' => 'prueba_flutter'],
    ]);

    $this->actingAs($administrator)
        ->get(route('diagnosticos.index'))
        ->assertOk()
        ->assertSeeText('Revisión automática de dispositivos')
        ->assertSeeText('PC-PANEL-DIAG-PC')
        ->assertSeeText('TAB-PANEL-DIAG-TAB')
        ->assertSeeText('Sin alertas en esta lectura')
        ->assertViewHas('equiposRevision', fn ($equipos): bool => $equipos->total() === 2);
});

test('teachers see all monitored equipment and assignment ownership while other panels stay scoped', function () {
    $administrator = User::factory()->administrador()->create();
    $teacher = createDevicePanelTeacher('Propio');
    $otherTeacher = createDevicePanelTeacher('Ajeno');
    $ownEquipment = createDevicePanelEquipment('PANEL-DOC-001', 'PC-PANEL-DOC-001');
    $otherEquipment = createDevicePanelEquipment('PANEL-AJENO-001', 'PC-PANEL-AJENO-001');
    $availableEquipment = createDevicePanelEquipment('PANEL-DISP-001', 'PC-PANEL-DISP-001');
    $ownEquipment->update(['token_hash' => hash('sha256', 'token-panel-docente')]);
    $ownAssignment = createDevicePanelAssignment($ownEquipment, $teacher['horario'], $administrator, 'Propio');
    $otherAssignment = createDevicePanelAssignment($otherEquipment, $otherTeacher['horario'], $administrator, 'Ajeno');
    $ownEquipment->activo->update(['estado_uso' => 'asignado']);
    $otherEquipment->activo->update(['estado_uso' => 'asignado']);

    foreach ([
        [$ownEquipment, $ownAssignment, 'PANEL-DOC-001'],
        [$otherEquipment, $otherAssignment, 'PANEL-AJENO-001'],
    ] as [$equipment, $assignment, $suffix]) {
        $heartbeat = Heartbeat::create([
            'equipo_id' => $equipment->id,
            'cpu_uso' => 25,
            'fecha_hora' => now()->subMinute(),
        ]);
        Diagnostico::create([
            'equipo_id' => $equipment->id,
            'heartbeat_id' => $heartbeat->id,
            'tipo' => 'Sistema',
            'nivel' => 'info',
            'codigo_regla' => 'PANEL_'.str_replace('-', '_', $suffix),
            'descripcion' => 'Diagnóstico '.$suffix,
            'fecha_hora' => now(),
        ]);
        Incidencia::create([
            'activo_id' => $equipment->activo_id,
            'asignacion_id' => $assignment->id,
            'tipo' => 'Hardware',
            'descripcion' => 'Incidencia '.$suffix,
            'prioridad' => 'media',
            'estado' => 'abierta',
            'origen' => 'app',
            'reportado_por' => $administrator->id,
            'fecha_reporte' => now(),
        ]);
    }

    $this->actingAs($teacher['user'])
        ->get(route('asignaciones.index'))
        ->assertOk()
        ->assertSeeText('PC-PANEL-DOC-001')
        ->assertSeeText('App conectada')
        ->assertSeeText('Vinculada')
        ->assertDontSeeText('PC-PANEL-AJENO-001');

    $this->actingAs($teacher['user'])
        ->get(route('monitoreo.index'))
        ->assertOk()
        ->assertSeeText('PANEL-DOC-001')
        ->assertSeeText('Estudiante Propio')
        ->assertSeeText('PANEL-DISP-001')
        ->assertSeeText('Disponible para asignación')
        ->assertSeeText('Nueva asignación')
        ->assertSeeText('PANEL-AJENO-001')
        ->assertSeeText('Materia Ajeno')
        ->assertSeeText('Docente Ajeno')
        ->assertViewHas('estadisticas', fn (array $stats): bool => $stats['total'] === 3);

    $this->actingAs($teacher['user'])
        ->get(route('diagnosticos.index'))
        ->assertOk()
        ->assertSeeText('Diagnóstico PANEL-DOC-001')
        ->assertDontSeeText('Diagnóstico PANEL-AJENO-001');

    $this->actingAs($teacher['user'])
        ->get(route('incidencias.index'))
        ->assertOk()
        ->assertSeeText('Incidencia PANEL-DOC-001')
        ->assertSeeText('App conectada')
        ->assertSeeText('Reportadas desde app')
        ->assertViewHas('estadisticas', fn (array $stats): bool => $stats['app'] === 1)
        ->assertDontSeeText('Incidencia PANEL-AJENO-001');

    $this->actingAs($teacher['user'])
        ->get(route('reportes.index'))
        ->assertOk()
        ->assertViewHas('estadisticas', fn (array $stats): bool => $stats['totalEquipos'] === 1
            && $stats['dispositivosConectados'] === 1
            && $stats['totalDiagnosticos'] === 1
            && $stats['incidenciasApp'] === 1);

    $this->withoutMiddleware(EnsureEmailIsVerified::class)
        ->actingAs($teacher['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('PANEL-DOC-001')
        ->assertSeeText('App vinculada')
        ->assertSeeText('App conectada')
        ->assertSeeText('Incidencias pendientes de mis asignaciones')
        ->assertSeeText('Incidencia PANEL-DOC-001')
        ->assertDontSeeText('PANEL-AJENO-001')
        ->assertDontSeeText('Incidencia PANEL-AJENO-001');
});

test('teachers cannot report incidents against active complement-only assignments', function () {
    $administrator = User::factory()->administrador()->create();
    $teacher = createDevicePanelTeacher('Complemento');
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Complemento',
        'matricula' => 'PANEL-MAT-COMPLEMENTO',
        'semestre' => 1,
        'ci' => fake()->unique()->numerify('########'),
        'estado' => 'activo',
    ]);
    $category = Categoria::create([
        'nombre' => 'Complementos de panel',
        'grupo' => 'electronico',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => 'PANEL-COMP-001',
        'categoria_id' => $category->id,
        'nombre' => 'Teclado de prueba',
        'estado_uso' => 'asignado',
    ]);
    $complement = Complemento::create([
        'activo_id' => $asset->id,
        'tipo_complemento' => 'Teclado USB',
        'estado_fisico' => 'bueno',
    ]);
    $assignment = Asignacion::create([
        'codigo' => 'PANEL-COMP-ASIG',
        'tipo' => 'asignado',
        'modalidad' => 'individual',
        'complemento_id' => $complement->id,
        'estudiante_id' => $student->id,
        'horario_id' => $teacher['horario']->id,
        'origen' => 'panel_web',
        'autorizado_por' => $administrator->id,
        'fecha_entrega' => now(),
        'fecha_devolucion_prevista' => now()->addHour(),
        'estado' => 'activa',
    ]);

    $this->actingAs($teacher['user'])
        ->from(route('incidencias.create'))
        ->post(route('incidencias.store'), [
            'asignacion_id' => $assignment->id,
            'tipo' => 'Hardware',
            'descripcion' => 'El recurso seleccionado no es un equipo monitoreable.',
            'prioridad' => 'media',
        ])
        ->assertRedirect(route('incidencias.create'))
        ->assertSessionHasErrors('asignacion_id');

    $this->assertDatabaseCount('incidencias', 0);
});

test('students cannot access the staff app monitoring panels', function () {
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Paneles',
        'matricula' => 'PANEL-EST-001',
        'semestre' => 1,
        'ci' => 'PANEL-CI-001',
        'estado' => 'activo',
    ]);
    $studentUser = User::factory()->estudiante($student)->create();

    $this->actingAs($studentUser)
        ->get(route('monitoreo.index'))
        ->assertForbidden();

    $this->actingAs($studentUser)
        ->get(route('diagnosticos.index'))
        ->assertForbidden();
});

function createDevicePanelEquipment(string $assetCode, string $identifier, string $equipmentType = 'cpu'): Equipo
{
    $category = Categoria::create([
        'nombre' => 'Categoría '.$assetCode,
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => $assetCode,
        'categoria_id' => $category->id,
        'nombre' => 'Equipo '.$assetCode,
        'estado_uso' => 'disponible',
    ]);

    return Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => $identifier,
        'tipo_equipo' => $equipmentType,
        'modo_uso' => 'individual',
    ]);
}

function createDevicePanelTeacher(string $suffix): array
{
    $teacherProfile = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => $suffix,
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    $user = User::factory()->docente($teacherProfile)->create();
    $subject = Materia::create([
        'nombre' => 'Materia '.$suffix,
        'semestre' => 'Primero',
        'estado' => 'activo',
    ]);
    $schedule = Horario::create([
        'gestion_id' => Gestion::activa()->id,
        'docente_id' => $teacherProfile->id,
        'materia_id' => $subject->id,
        'dia_semana' => 1,
        'hora_inicio' => $suffix === 'Propio' ? '08:00:00' : '10:00:00',
        'hora_fin' => $suffix === 'Propio' ? '09:00:00' : '11:00:00',
        'aula' => 'Aula '.$suffix,
        'estado' => 'activo',
    ]);

    return ['user' => $user, 'horario' => $schedule];
}

function createDevicePanelAssignment(Equipo $equipment, Horario $schedule, User $administrator, string $suffix): Asignacion
{
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => $suffix,
        'matricula' => 'PANEL-MAT-'.$suffix,
        'semestre' => 1,
        'ci' => fake()->unique()->numerify('########'),
        'estado' => 'activo',
    ]);

    return Asignacion::create([
        'codigo' => 'PANEL-ASIG-'.$suffix,
        'tipo' => 'asignado',
        'modalidad' => 'individual',
        'equipo_id' => $equipment->id,
        'estudiante_id' => $student->id,
        'horario_id' => $schedule->id,
        'origen' => 'panel_web',
        'autorizado_por' => $administrator->id,
        'fecha_entrega' => now()->subHour(),
        'fecha_devolucion_prevista' => now()->addHour(),
        'estado' => 'activa',
    ]);
}
