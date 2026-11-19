<?php

use App\Models\Activo;
use App\Models\Alerta;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Diagnostico;
use App\Models\Docente;
use App\Models\Equipo;
use App\Models\Estudiante;
use App\Models\Gestion;
use App\Models\Heartbeat;
use App\Models\HistorialSeguimientoEstudiante;
use App\Models\Horario;
use App\Models\Incidencia;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->withoutVite();
});

test('academic reports include graduates and freezes without active enrollments', function () {
    $admin = User::factory()->administrador()->create();
    $gestion = Gestion::activa();
    $egresado = Estudiante::create([
        'nombres' => 'Egresado',
        'apellido_paterno' => 'Reporte',
        'matricula' => fake()->unique()->numerify('EGRE#####'),
        'semestre' => 'Cuarto',
        'ci' => fake()->unique()->numerify('########'),
        'estado' => 'egresado',
    ]);
    $congelado = Estudiante::create([
        'nombres' => 'Congelado',
        'apellido_paterno' => 'Reporte',
        'matricula' => fake()->unique()->numerify('CONG#####'),
        'semestre' => 'Primero',
        'ci' => fake()->unique()->numerify('########'),
        'estado' => 'congelado',
    ]);
    HistorialSeguimientoEstudiante::create([
        'estudiante_id' => $egresado->id,
        'gestion_id' => $gestion->id,
        'tipo_evento' => 'egreso',
        'fecha_evento' => now()->toDateString(),
        'registrado_por' => $admin->id,
    ]);
    HistorialSeguimientoEstudiante::create([
        'estudiante_id' => $congelado->id,
        'gestion_id' => $gestion->id,
        'tipo_evento' => 'congelamiento',
        'fecha_evento' => now()->toDateString(),
        'registrado_por' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('reportes.index', ['gestion' => $gestion->codigo]))
        ->assertSeeText('Egresados')
        ->assertSeeText('Congelamientos registrados')
        ->assertViewHas('reporteAcademico', fn (array $reporte): bool => $reporte['total'] === 0
            && $reporte['progresion']['egresados'] === 1
            && $reporte['progresion']['congelamientos'] === 1
            && $reporte['tiene_movimientos']);
});

test('administrators can review academic statistics for the selected active period', function () {
    $perfilAdministrador = Docente::create([
        'nombres' => 'Administrador',
        'apellido_paterno' => 'Académico',
        'apellido_materno' => 'Reportes',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Administradora',
        'estado' => 'activo',
    ]);
    $admin = User::factory()->administrador()->create([
        'docente_id' => $perfilAdministrador->id,
    ]);
    $gestion = Gestion::activa();
    $materia = Materia::create([
        'nombre' => 'Análisis de sistemas',
        'sigla' => 'AS-01',
        'semestre' => 'Primero',
        'estado' => 'activo',
    ]);

    $aprobado = reporteAcademicEnrollment($materia, 'Aprobado');
    $aprobado->update([
        'parcial_1' => 80,
        'parcial_2' => 90,
        'nota_final' => 85,
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
        'resultado_final' => 'Aprobado',
        'revisado_por' => $admin->id,
        'fecha_revision' => now(),
    ]);

    $pendiente = reporteAcademicEnrollment($materia, 'Cursando');
    $pendiente->update([
        'parcial_1' => 70,
        'resultado_reportado' => 'Aprobado',
        'resultado_reportado_por' => $admin->id,
        'fecha_reporte' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('reportes.index', ['gestion' => $gestion->codigo]))
        ->assertOk()
        ->assertSeeText('Reporte académico')
        ->assertSee('id="gestion-academica"', false)
        ->assertSeeText('Generar PDF')
        ->assertSeeText('Frecuencia del PDF')
        ->assertSeeText('Análisis de sistemas')
        ->assertSeeText('En revisión administrativa')
        ->assertSee('name="gestion" value="'.$gestion->codigo.'"', false)
        ->assertViewHas('reporteAcademico', function (array $reporte): bool {
            return $reporte['total'] === 2
                && $reporte['cursando'] === 1
                && $reporte['en_revision'] === 1
                && $reporte['calificaciones_pendientes'] === 1
                && $reporte['aprobados'] === 1
                && $reporte['materias']->first()->total === 2;
        });
});

test('administrators can review academic summaries from archived periods', function () {
    $admin = User::factory()->administrador()->create();
    $materia = [
        'materia_id' => 27,
        'materia_nombre' => 'Redes avanzadas',
        'semestre' => 'Segundo',
    ];

    DB::table('inscripciones_archivadas')->insert([
        [
            'gestion' => 'I-2026',
            'inscripcion_original_id' => 1,
            'estudiante_id' => 12,
            'estudiante_nombre' => 'Estudiante aprobado',
            'matricula' => 'MAT-012',
            ...$materia,
            'tipo' => 'Normal',
            'resultado_final' => 'Aprobado',
            'nota_final' => 80,
            'decision_progresion' => 'avanza',
            'datos_inscripcion' => '{}',
            'archivado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'gestion' => 'I-2026',
            'inscripcion_original_id' => 2,
            'estudiante_id' => 13,
            'estudiante_nombre' => 'Estudiante reprobado',
            'matricula' => 'MAT-013',
            ...$materia,
            'tipo' => 'Arrastre',
            'resultado_final' => 'Reprobado',
            'nota_final' => 45,
            'decision_progresion' => 'repite_semestre',
            'datos_inscripcion' => '{}',
            'archivado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('reportes.index', ['gestion' => 'I-2026']))
        ->assertOk()
        ->assertSeeText('Periodo archivado')
        ->assertSeeText('Redes avanzadas')
        ->assertSeeText('Estudiantes que progresan')
        ->assertViewHas('reporteAcademico', function (array $reporte): bool {
            return $reporte['archivado']
                && $reporte['total'] === 2
                && $reporte['aprobados'] === 1
                && $reporte['reprobados'] === 1
                && $reporte['progresan'] === 1
                && (float) $reporte['promedio'] === 62.5;
        });
});

test('administrators see inventory and recent activity in the reports view', function () {
    $admin = User::factory()->administrador()->create();
    $category = Categoria::create([
        'nombre' => 'Inventario de reportes',
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $activo = Activo::create([
        'codigo' => 'ACT-REPORT-001',
        'categoria_id' => $category->id,
        'nombre' => 'Equipo para reportes',
        'marca' => 'Marca',
        'modelo' => 'Modelo',
        'estado_uso' => 'asignado',
    ]);
    $equipo = Equipo::create([
        'activo_id' => $activo->id,
        'identificador' => 'IT-REPORT-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    $docente = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Reportes',
        'apellido_materno' => 'Prueba',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    $estudiante = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Reportes',
        'apellido_materno' => 'Prueba',
        'matricula' => fake()->unique()->numerify('MAT#####'),
        'semestre' => 'Primero',
        'ci' => fake()->unique()->numerify('########'),
        'estado' => 'activo',
    ]);
    $materia = Materia::create([
        'nombre' => 'Laboratorio de redes',
        'sigla' => 'LAB-RED',
        'semestre' => 'Primero',
        'estado' => 'activo',
    ]);
    $horario = Horario::create([
        'gestion_id' => Gestion::activa()->id,
        'gestion' => Gestion::activa()->codigo,
        'docente_id' => $docente->id,
        'materia_id' => $materia->id,
        'dia_semana' => 'Lunes',
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => 'activo',
    ]);
    $asignacion = Asignacion::create([
        'codigo' => 'ASIG-REPORT-001',
        'tipo' => 'asignado',
        'modalidad' => 'individual',
        'equipo_id' => $equipo->id,
        'estudiante_id' => $estudiante->id,
        'horario_id' => $horario->id,
        'autorizado_por' => $admin->id,
        'fecha_entrega' => now(),
        'fecha_devolucion_prevista' => now()->addDay(),
        'estado' => 'activa',
    ]);
    Incidencia::create([
        'activo_id' => $activo->id,
        'asignacion_id' => $asignacion->id,
        'tipo' => 'Hardware',
        'descripcion' => 'Revisión preventiva del equipo.',
        'prioridad' => 'alta',
        'estado' => 'abierta',
        'origen' => 'manual',
        'reportado_por' => $admin->id,
        'fecha_reporte' => now(),
    ]);
    $heartbeat = Heartbeat::create([
        'equipo_id' => $equipo->id,
        'cpu_uso' => 95,
        'fecha_hora' => now(),
    ]);
    Diagnostico::create([
        'equipo_id' => $equipo->id,
        'heartbeat_id' => $heartbeat->id,
        'tipo' => 'CPU',
        'nivel' => 'critica',
        'codigo_regla' => 'CPU_ALTA',
        'descripcion' => 'Uso de CPU superior al 90%.',
        'fecha_hora' => now(),
    ]);
    Alerta::create([
        'equipo_id' => $equipo->id,
        'codigo_regla' => 'CPU_ALTA',
        'severidad' => 'critica',
        'mensaje' => 'Uso de CPU superior al 90%.',
        'estado' => 'abierta',
        'abierta_en' => now(),
    ]);
    Incidencia::create([
        'activo_id' => $activo->id,
        'tipo' => 'Red',
        'descripcion' => 'La app reportó una desconexión de red.',
        'prioridad' => 'media',
        'estado' => 'abierta',
        'origen' => 'app',
        'fecha_reporte' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('reportes.index'))
        ->assertOk()
        ->assertSeeText('ACT-REPORT-001')
        ->assertSeeText('Estudiante Reportes Prueba')
        ->assertSeeText('Laboratorio de redes')
        ->assertSeeText('Revisión preventiva del equipo.')
        ->assertSeeText('Estado de apps y diagnósticos')
        ->assertSeeText('Desde apps')
        ->assertViewHas('estadisticas', fn (array $stats): bool => $stats['totalEquipos'] === 1
            && $stats['equiposEnUso'] === 1
            && $stats['totalAsignaciones'] === 1
            && $stats['asignacionesActivas'] === 1
            && $stats['totalIncidencias'] === 2
            && $stats['incidenciasPendientes'] === 2
            && $stats['incidenciasApp'] === 1
            && $stats['dispositivosConectados'] === 1
            && $stats['dispositivosDesconectados'] === 0
            && $stats['dispositivosSinReportes'] === 0
            && $stats['totalDiagnosticos'] === 1
            && $stats['diagnosticosCriticos'] === 1
            && $stats['alertasAbiertas'] === 1);
});

test('teachers keep their existing reports view without administrative academic data', function () {
    $docente = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Reportes',
        'apellido_materno' => 'Prueba',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    $teacher = User::factory()->docente($docente)->create();

    $this->actingAs($teacher)
        ->get(route('reportes.index'))
        ->assertOk()
        ->assertSeeText('Reportes del sistema')
        ->assertSeeText('Generar PDF')
        ->assertDontSeeText('Reporte académico')
        ->assertViewHas('reporteAcademico', null);
});

test('administrators can download weekly, monthly and yearly summaries for the selected date', function (
    string $tipoPeriodo,
    string $fechaPeriodo,
    string $nombreArchivo,
) {
    $admin = User::factory()->administrador()->create();

    $response = $this->actingAs($admin)->get(route('reportes.pdf', [
        'tipo_periodo' => $tipoPeriodo,
        'fecha_periodo' => $fechaPeriodo,
    ]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain($nombreArchivo)
        ->and($response->getContent())->toStartWith('%PDF-');
})->with([
    'week uses Monday through Sunday' => [
        'semanal',
        '2026-11-17',
        'reporte-semanal-2026-11-16-a-2026-11-22.pdf',
    ],
    'month uses the calendar month' => [
        'mensual',
        '2026-02-13',
        'reporte-mensual-2026-02-01-a-2026-02-28.pdf',
    ],
    'year uses the calendar year' => [
        'anual',
        '2024-06-15',
        'reporte-anual-2024-01-01-a-2024-12-31.pdf',
    ],
]);

test('teachers can download a report PDF for their permitted summary', function () {
    $docente = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Reporte PDF',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);
    $teacher = User::factory()->docente($docente)->create();

    $this->actingAs($teacher)
        ->get(route('reportes.pdf', [
            'tipo_periodo' => 'mensual',
            'fecha_periodo' => '2026-11-17',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('students cannot download system report PDFs', function () {
    $student = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Sin acceso',
        'matricula' => fake()->unique()->numerify('MAT#####'),
        'semestre' => 'Primero',
        'ci' => fake()->unique()->numerify('########'),
        'estado' => 'activo',
    ]);
    $studentUser = User::factory()->estudiante($student)->create();

    $this->actingAs($studentUser)
        ->get(route('reportes.pdf', [
            'tipo_periodo' => 'anual',
            'fecha_periodo' => '2026-11-17',
        ]))
        ->assertForbidden();
});

test('invalid report period choices are rejected', function () {
    $admin = User::factory()->administrador()->create();

    $this->actingAs($admin)
        ->from(route('reportes.index'))
        ->get(route('reportes.pdf', [
            'tipo_periodo' => 'diario',
            'fecha_periodo' => '2026-11-17',
        ]))
        ->assertSessionHasErrors('tipo_periodo');
});

test('administrators cannot select a malformed academic period in reports', function () {
    $admin = User::factory()->administrador()->create();

    $this->actingAs($admin)
        ->from(route('reportes.index'))
        ->get(route('reportes.index', ['gestion' => 'wrong-period']))
        ->assertSessionHasErrors('gestion');

    $this->actingAs($admin)
        ->from(route('reportes.index'))
        ->get(route('reportes.index', ['gestion' => 'I-1900']))
        ->assertSessionHasErrors('gestion');
});

function reporteAcademicEnrollment(Materia $materia, string $estado): Inscripcion
{
    $gestion = Gestion::activa();
    $suffix = fake()->unique()->numerify('#####');
    $estudiante = Estudiante::create([
        'nombres' => 'Estudiante',
        'apellido_paterno' => 'Reporte',
        'apellido_materno' => 'Prueba',
        'matricula' => "MAT{$suffix}",
        'semestre' => $materia->semestre,
        'ci' => fake()->unique()->numerify('########'),
        'estado' => 'activo',
    ]);

    return Inscripcion::create([
        'estudiante_id' => $estudiante->id,
        'materia_id' => $materia->id,
        'gestion_id' => $gestion->id,
        'gestion' => $gestion->codigo,
        'tipo' => 'Normal',
        'estado' => $estado,
        'fecha_inscripcion' => now()->toDateString(),
    ]);
}
