<?php

use App\Models\User;
use App\Services\SystemShutdownService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->frameworkDirectory = sys_get_temp_dir()
        .DIRECTORY_SEPARATOR
        .'system-shutdown-'
        .Str::uuid();

    File::ensureDirectoryExists($this->frameworkDirectory);
    $this->app->instance(
        SystemShutdownService::class,
        new SystemShutdownService($this->frameworkDirectory)
    );
});

afterEach(function (): void {
    File::deleteDirectory($this->frameworkDirectory);
});

test('administrators can request local system shutdown once', function (): void {
    $heartbeatPath = $this->frameworkDirectory.DIRECTORY_SEPARATOR.'system-shutdown-agent.heartbeat';
    File::put($heartbeatPath, (string) time());

    $administrator = User::factory()->administrador()->create();

    $this->actingAs($administrator)
        ->postJson(route('system.shutdown'))
        ->assertStatus(202)
        ->assertJsonPath('message', 'Solicitud recibida. El sistema se está finalizando.');

    expect(File::exists($this->frameworkDirectory.DIRECTORY_SEPARATOR.'system-shutdown.request'))->toBeTrue();

    $this->actingAs($administrator)
        ->postJson(route('system.shutdown'))
        ->assertConflict();
});

test('teachers can request local system shutdown', function (): void {
    File::put(
        $this->frameworkDirectory.DIRECTORY_SEPARATOR.'system-shutdown-agent.heartbeat',
        (string) time()
    );
    $teacher = User::factory()->create(['tipo_usuario' => 'docente']);

    $this->actingAs($teacher)
        ->postJson(route('system.shutdown'))
        ->assertStatus(202);
});

test('students cannot request local system shutdown', function (): void {
    $student = User::factory()->create(['tipo_usuario' => 'estudiante']);

    $this->actingAs($student)
        ->postJson(route('system.shutdown'))
        ->assertForbidden();
});

test('guests are redirected when requesting local system shutdown', function (): void {
    $this->post(route('system.shutdown'))
        ->assertRedirect(route('login'));
});

test('shutdown request is unavailable when the local agent heartbeat is missing or stale', function (): void {
    $administrator = User::factory()->administrador()->create();

    $this->actingAs($administrator)
        ->postJson(route('system.shutdown'))
        ->assertStatus(503);

    $heartbeatPath = $this->frameworkDirectory.DIRECTORY_SEPARATOR.'system-shutdown-agent.heartbeat';
    File::put($heartbeatPath, (string) (time() - 10));
    touch($heartbeatPath, time() - 10);

    $this->actingAs($administrator)
        ->postJson(route('system.shutdown'))
        ->assertStatus(503);

    expect(File::exists($this->frameworkDirectory.DIRECTORY_SEPARATOR.'system-shutdown.request'))->toBeFalse();
});

test('staff dashboards show the system shutdown control', function (): void {
    $administrator = User::factory()->administrador()->create();
    $teacher = User::factory()->create(['tipo_usuario' => 'docente']);

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Finalizar sistema');

    $this->actingAs($teacher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Finalizar sistema');
});
