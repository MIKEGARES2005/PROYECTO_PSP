<?php

use App\Models\Activo;
use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Docente;
use App\Models\Equipo;
use App\Models\Estudiante;
use App\Models\Heartbeat;
use App\Models\Notificacion;
use App\Models\ReporteGenerado;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('purges expired temporary data and retains records within their grace periods', function () {
    $this->travelTo('2026-11-02 12:00:00');
    Storage::fake('public');

    $admin = User::factory()->administrador()->create();
    $category = Categoria::create([
        'nombre' => 'Equipos para depuración',
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => 'PURGE-001',
        'categoria_id' => $category->id,
        'nombre' => 'Equipo retenido',
        'estado_uso' => 'disponible',
    ]);
    $equipment = Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'PURGE-EQ-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    $oldHeartbeat = Heartbeat::create([
        'equipo_id' => $equipment->id,
        'fecha_hora' => now()->subDays(100),
    ]);

    DB::table('sessions')->insert([
        ['id' => 'expired-session', 'user_id' => $admin->id, 'payload' => 'expired', 'last_activity' => now()->subMinutes((int) config('session.lifetime') + 1)->timestamp],
        ['id' => 'active-session', 'user_id' => $admin->id, 'payload' => 'active', 'last_activity' => now()->timestamp],
    ]);
    DB::table('cache')->insert([
        ['key' => 'expired-cache', 'value' => 'expired', 'expiration' => now()->subMinute()->timestamp],
        ['key' => 'active-cache', 'value' => 'active', 'expiration' => now()->addHour()->timestamp],
    ]);
    DB::table('cache_locks')->insert([
        ['key' => 'expired-lock', 'owner' => 'owner', 'expiration' => now()->subMinute()->timestamp],
        ['key' => 'active-lock', 'owner' => 'owner', 'expiration' => now()->addHour()->timestamp],
    ]);

    Storage::disk('public')->put('reportes/expirado.pdf', 'reporte vencido');
    $expiredReport = ReporteGenerado::create([
        'tipo' => 'otro',
        'generado_por' => $admin->id,
        'ruta' => 'reportes/expirado.pdf',
        'vence_en' => now()->subDays(31),
    ]);
    $reportInGracePeriod = ReporteGenerado::create([
        'tipo' => 'otro',
        'generado_por' => $admin->id,
        'ruta' => 'reportes/en-gracia.pdf',
        'vence_en' => now()->subDays(29),
    ]);

    $expiredQr = CodigoQr::create([
        'tipo' => 'clase',
        'token_hash' => hash('sha256', 'qr-expirado'),
        'expira_en' => now()->subDays(31),
        'activo' => true,
        'creado_por' => $admin->id,
    ]);
    $qrInGracePeriod = CodigoQr::create([
        'tipo' => 'clase',
        'token_hash' => hash('sha256', 'qr-en-gracia'),
        'expira_en' => now()->subDays(29),
        'activo' => true,
        'creado_por' => $admin->id,
    ]);
    $inactiveQr = CodigoQr::create([
        'tipo' => 'clase',
        'token_hash' => hash('sha256', 'qr-inactivo'),
        'activo' => false,
        'creado_por' => $admin->id,
    ]);
    DB::table('codigos_qr')
        ->where('id', $inactiveQr->id)
        ->update(['updated_at' => now()->subDays(31)]);

    $this->artisan('system:purge-expired-data')->assertExitCode(0);

    $this->assertDatabaseMissing('sessions', ['id' => 'expired-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'active-session']);
    $this->assertDatabaseMissing('cache', ['key' => 'expired-cache']);
    $this->assertDatabaseHas('cache', ['key' => 'active-cache']);
    $this->assertDatabaseMissing('cache_locks', ['key' => 'expired-lock']);
    $this->assertDatabaseHas('cache_locks', ['key' => 'active-lock']);
    $this->assertModelMissing($expiredReport);
    $this->assertModelExists($reportInGracePeriod);
    Storage::disk('public')->assertMissing('reportes/expirado.pdf');
    $this->assertModelMissing($expiredQr);
    $this->assertModelMissing($inactiveQr);
    $this->assertModelExists($qrInGracePeriod);
    $this->assertModelExists($oldHeartbeat);
});

it('shows administrators the active retention policy and its safeguards', function () {
    config([
        'session.lifetime' => 45,
        'retention.notifications.read_after_days' => 7,
        'retention.users.deletion_grace_period_days' => 3,
        'retention.bitacora.period_months' => 4,
    ]);

    $admin = User::factory()->administrador()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSeeText('Política de purgado del sistema')
        ->assertSeeText('Ejecución automática diaria · 02:00 (Bolivia)')
        ->assertSeeText('límite configurado de 45 minutos')
        ->assertSeeText('las leídas se purgan después de 7 días')
        ->assertSeeText('las no leídas se conservan mientras la cuenta siga activa')
        ->assertSeeText('restaurables durante 3 días')
        ->assertSeeText('el administrador puede finalizar la eliminación antes')
        ->assertSeeText('El PDF original, que contiene datos personales, se conserva indefinidamente')
        ->assertSeeText('se conservan los registros académicos y operativos')
        ->assertSeeText('90 días de datos crudos es una meta aún no implementada')
        ->assertSeeText('La referencia configurada de 4 meses todavía no se aplica')
        ->assertSeeText('30 días de gracia')
        ->assertSeeText('se eliminan 30 días después de su vencimiento');
});

it('purges old read notifications and anonymizes expired deleted profiles', function () {
    $this->travelTo('2026-11-02 12:00:00');
    Storage::fake('public');
    $reportOwner = User::factory()->administrador()->create();

    $student = Estudiante::create([
        'nombres' => 'Ana',
        'apellido_paterno' => 'Prueba',
        'matricula' => 'MAT-PURGE-001',
        'semestre' => 1,
        'ci' => 'PURGE-CI-001',
        'estado' => 'activo',
    ]);
    $photoPath = 'fotos/estudiantes/perfil-vencido.png';
    Storage::disk('public')->put($photoPath, 'foto personal');
    $student->update(['foto_personal' => $photoPath]);
    $user = User::factory()->estudiante($student)->create();
    $user->preguntasSeguridad()->create([
        'pregunta' => 'Pregunta',
        'respuesta_hash' => 'hash',
    ]);
    $user->delete();
    $student->delete();
    DB::table('users')->where('id', $user->id)->update(['deleted_at' => now()->subDays(3)]);
    DB::table('estudiantes')->where('id', $student->id)->update(['deleted_at' => now()->subDays(3)]);
    $activeAdmin = User::factory()->administrador()->create();
    $pdfPath = 'reportes/usuarios-eliminados/estudiante-vencido.pdf';
    Storage::disk('public')->put($pdfPath, 'PDF personal');
    $personalReport = ReporteGenerado::create([
        'tipo' => 'otro',
        'parametros' => [
            'tipo' => 'eliminacion_usuario',
            'usuario_id' => $user->id,
        ],
        'generado_por' => $reportOwner->id,
        'ruta' => $pdfPath,
        'vence_en' => null,
    ]);

    $oldNotification = Notificacion::create([
        'user_id' => $user->id,
        'tipo' => 'sistema',
        'titulo' => 'Antigua',
        'mensaje' => 'Debe purgarse.',
        'leida_en' => now()->subDays(6),
    ]);
    $recentNotification = Notificacion::create([
        'user_id' => $activeAdmin->id,
        'tipo' => 'sistema',
        'titulo' => 'Reciente',
        'mensaje' => 'Debe conservarse.',
        'leida_en' => now()->subDays(4),
    ]);

    $this->artisan('system:purge-expired-data')
        ->expectsOutput('usuarios_anonimizados: 1 perfil(es) anonimizados.')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('notificaciones', ['id' => $oldNotification->id]);
    $this->assertDatabaseHas('notificaciones', ['id' => $recentNotification->id]);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'username' => 'eliminado_'.$user->id,
        'activo' => false,
    ]);
    expect(DB::table('users')->where('id', $user->id)->value('anonymized_at'))->not->toBeNull();
    $this->assertDatabaseHas('estudiantes', [
        'id' => $student->id,
        'nombres' => 'Usuario eliminado',
        'matricula' => null,
        'foto_personal' => null,
        'estado' => 'retirado',
    ]);
    $this->assertDatabaseMissing('preguntas_seguridad', ['user_id' => $user->id]);
    $this->assertModelExists($personalReport);
    Storage::disk('public')->assertMissing($photoPath);
    Storage::disk('public')->assertExists($pdfPath);
});

it('anonymizes a soft-deleted administrator without affecting the other administrator', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $docente = Docente::create([
        'nombres' => 'Administrador',
        'apellido_paterno' => 'Antiguo',
        'ci' => 'PURGE-ADMIN-001',
        'cargo' => 'Administrador',
        'estado' => 'activo',
    ]);
    $admin = User::factory()->administrador()->create([
        'username' => 'admin',
        'docente_id' => $docente->id,
    ]);
    $secondAdmin = User::factory()->administrador()->create([
        'username' => 'segundo-admin',
    ]);
    $admin->preguntasSeguridad()->create([
        'pregunta' => 'Pregunta de prueba',
        'respuesta_hash' => 'hash',
    ]);
    $notification = Notificacion::create([
        'user_id' => $admin->id,
        'tipo' => 'sistema',
        'titulo' => 'Aviso de prueba',
        'mensaje' => 'Notificación del administrador.',
    ]);
    DB::table('sessions')->insert([
        'id' => 'expired-admin-session',
        'user_id' => $admin->id,
        'payload' => 'session-payload',
        'last_activity' => now()->timestamp,
    ]);

    $admin->delete();
    $docente->delete();
    DB::table('users')->where('id', $admin->id)->update(['deleted_at' => now()->subDays(3)]);
    DB::table('docentes')->where('id', $docente->id)->update(['deleted_at' => now()->subDays(3)]);

    $this->artisan('system:purge-expired-data')->assertExitCode(0);

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'username' => 'eliminado_'.$admin->id,
        'tipo_usuario' => 'administrador',
        'activo' => false,
    ]);
    $this->assertDatabaseHas('docentes', [
        'id' => $docente->id,
        'nombres' => 'Usuario eliminado',
        'ci' => 'ELIM-'.$admin->id,
        'estado' => 'inactivo',
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $secondAdmin->id,
        'username' => 'segundo-admin',
        'activo' => true,
        'deleted_at' => null,
    ]);
    $this->assertDatabaseMissing('preguntas_seguridad', ['user_id' => $admin->id]);
    $this->assertDatabaseMissing('notificaciones', ['id' => $notification->id]);
    $this->assertDatabaseMissing('sessions', ['id' => 'expired-admin-session']);
});

it('does not show the administration retention policy to teachers', function () {
    $teacherProfile = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Prueba',
        'ci' => 'PURGE-TEACHER-001',
        'cargo' => 'Docente',
    ]);
    $teacher = User::factory()->docente($teacherProfile)->create();

    $this->actingAs($teacher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSeeText('Política de purgado del sistema');
});
