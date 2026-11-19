<?php

use App\Http\Controllers\AccesorioController;
use App\Http\Controllers\ActivoController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiagnosticoController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EstudianteDashboardController;
use App\Http\Controllers\EstudianteEquipoController;
use App\Http\Controllers\EstudianteQrController;
use App\Http\Controllers\EstudianteSeguimientoController;
use App\Http\Controllers\GestionAcademicaController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\InvitadoController;
use App\Http\Controllers\MateriaController;
use App\Http\Controllers\MonitoreoController;
use App\Http\Controllers\PanelAcademicoController;
use App\Http\Controllers\PanelEstudiantilController;
use App\Http\Controllers\PanelSeguimientoDocenteController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicActivoFichaController;
use App\Http\Controllers\PublicEquipoFichaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SecurityQuestionController;
use App\Http\Controllers\SeguimientoEstudiantilController;
use App\Http\Controllers\SolicitudEquipoController;
use App\Http\Controllers\SolicitudRegistroController;
use App\Http\Controllers\StudentNotificationController;
use App\Http\Controllers\SystemShutdownController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/ficha/equipo/{equipo}', [PublicEquipoFichaController::class, 'show'])
    ->name('equipos.ficha-publica');
Route::get('/ficha/activo/{activo}', [PublicActivoFichaController::class, 'show'])
    ->name('activos.ficha-publica');
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'role:administrador,docente'])
    ->name('dashboard');
Route::post('/system/shutdown', SystemShutdownController::class)
    ->middleware(['auth', 'role:administrador,docente'])
    ->name('system.shutdown');
Route::middleware(['auth:student', 'role:estudiante,student'])->get('/estudiante', [EstudianteDashboardController::class, 'index'])
    ->name('estudiante.dashboard');
Route::middleware(['auth:student', 'role:estudiante,student'])
    ->prefix('estudiante/notificaciones')
    ->name('estudiante.notifications.')
    ->group(function () {
        Route::get('/detalles', [StudentNotificationController::class, 'details'])->name('details');
        Route::get('/', [StudentNotificationController::class, 'index'])->name('index');
        Route::post('/limpiar', [StudentNotificationController::class, 'clear'])->name('clear');
    });
Route::middleware(['auth:student', 'role:estudiante,student'])
    ->prefix('estudiante')
    ->name('estudiante.')
    ->group(function () {
        Route::get('/equipos/solicitar', [EstudianteEquipoController::class, 'create'])->name('equipment.create');
        Route::post('/equipos/solicitar', [EstudianteEquipoController::class, 'store'])->name('equipment.store');
        Route::get('/prestamos', [EstudianteEquipoController::class, 'loans'])->name('loans.index');
        Route::post('/prestamos/{asignacion}/devolucion', [EstudianteEquipoController::class, 'requestReturn'])->name('loans.return');
        Route::get('/prestamos/{asignacion}/reporte', [EstudianteEquipoController::class, 'report'])->name('loans.report');
        Route::get('/seguimiento', [EstudianteSeguimientoController::class, 'index'])->name('followup.index');
    });
Route::get('/estudiante/qr', [EstudianteQrController::class, 'show'])->name('estudiante.qr');
Route::get('/estudiante/qr/status', [EstudianteQrController::class, 'status'])
    ->name('estudiante.qr.status');
Route::post('/estudiante/qr/reconnect', [EstudianteQrController::class, 'reconnect'])
    ->name('estudiante.qr.reconnect');
Route::get('/estudiante/qr/image', [EstudianteQrController::class, 'image'])
    ->name('estudiante.qr.image');
Route::get('/estudiante/qr/download', [EstudianteQrController::class, 'download'])
    ->name('estudiante.qr.download');
Route::prefix('registro/aspirante')->name('registro.aspirante.')->group(function () {
    Route::get('/', [SolicitudRegistroController::class, 'createPublic'])->name('create');
    Route::post('/', [SolicitudRegistroController::class, 'storePublic'])->name('store');
    Route::get('/estado/{token}', [SolicitudRegistroController::class, 'status'])->name('status');
    Route::get('/estado/{token}/actualizar', [SolicitudRegistroController::class, 'statusData'])->name('status.data');
    Route::post('/estado/{token}/ingresar', [SolicitudRegistroController::class, 'loginApproved'])->name('login-approved');
    Route::post('/{token}/cancelar', [SolicitudRegistroController::class, 'cancel'])->name('cancel');
});
Route::middleware(['auth', 'role:administrador'])
    ->prefix('admin/solicitudes')
    ->name('admin.solicitudes.')
    ->group(function () {
        Route::get('/', [SolicitudRegistroController::class, 'indexAdmin'])->name('index');
        Route::get('/credenciales', [SolicitudRegistroController::class, 'credentials'])->name('credentials');
        Route::post('/credenciales/cerrar', [SolicitudRegistroController::class, 'clearCredentials'])->name('credentials.clear');
        Route::post('/aprobar-multiples', [SolicitudRegistroController::class, 'aprobarMultiples'])->name('aprobar-multiples');
        Route::post('/{id}/aprobar', [SolicitudRegistroController::class, 'aprobar'])->name('aprobar');
        Route::post('/{id}/rechazar', [SolicitudRegistroController::class, 'rechazar'])->name('rechazar');
    });
Route::middleware(['auth', 'role:administrador'])->prefix('admin/bitacora')->name('admin.bitacora.')->group(function () {
    Route::get('/', [BitacoraController::class, 'index'])->name('index');
});
Route::middleware(['auth', 'role:administrador,docente'])->prefix('admin/notificaciones')->name('admin.notifications.')->group(function () {
    Route::get('/', [AdminNotificationController::class, 'index'])->name('index');
    Route::post('/limpiar', [AdminNotificationController::class, 'clear'])->name('clear');
});
Route::middleware(['auth', 'role:administrador,docente'])->group(function () {
    Route::get('/solicitudes-equipos', [SolicitudEquipoController::class, 'index'])->name('solicitudes.equipos.index');
    Route::post('/solicitudes-equipos/asignaciones/{asignacion}/aprobar-devolucion', [SolicitudEquipoController::class, 'approveReturn'])->name('solicitudes.equipos.return.approve');
    Route::post('/solicitudes-equipos/{solicitud}/aprobar', [SolicitudEquipoController::class, 'approve'])->name('solicitudes.equipos.approve');
    Route::post('/solicitudes-equipos/{solicitud}/rechazar', [SolicitudEquipoController::class, 'reject'])->name('solicitudes.equipos.reject');
    Route::get('/monitoreo', [MonitoreoController::class, 'index'])->name('monitoreo.index');
    Route::get('/diagnosticos', [DiagnosticoController::class, 'index'])->name('diagnosticos.index');
});
Route::middleware(['auth', 'role:administrador'])
    ->prefix('monitoreo/equipos')
    ->name('monitoreo.equipos.')
    ->group(function () {
        Route::post('/{equipo}/vinculacion', [MonitoreoController::class, 'storePairingCode'])
            ->name('vinculacion.store');
        Route::delete('/{equipo}/vinculacion', [MonitoreoController::class, 'destroyPairing'])
            ->name('vinculacion.destroy');
    });
Route::middleware(['auth', 'role:administrador'])->group(function () {
    Route::get('/gestion-academica', [GestionAcademicaController::class, 'indexGestiones'])
        ->name('gestion-academica.index');
    Route::get('/gestion-academica/progresion', [GestionAcademicaController::class, 'progresion'])
        ->name('gestion-academica.progresion');
    Route::post('/gestion-academica/estudiantes/{estudiante}/progresion', [GestionAcademicaController::class, 'confirmarProgresion'])
        ->name('gestion-academica.progresion.confirmar');
    Route::post('/gestion-academica/generar-inscripciones', [GestionAcademicaController::class, 'generarInscripciones'])
        ->name('gestion-academica.inscripciones.generar');
    Route::get('/gestion-academica/{semestre}', [GestionAcademicaController::class, 'semestre'])
        ->name('gestion-academica.semestre');
    Route::get('/gestion-academica/{semestre}/materias/{materia}', [GestionAcademicaController::class, 'materia'])
        ->name('gestion-academica.materia');
    Route::get('/gestion-academica/historial/{gestion}', [GestionAcademicaController::class, 'historial'])
        ->name('gestion-academica.historial');

    Route::post('/gestion-academica/iniciar', [GestionAcademicaController::class, 'iniciarGestion'])
        ->name('gestion-academica.iniciar');
    Route::post('/gestion-academica/cerrar', [GestionAcademicaController::class, 'cerrarGestion'])
        ->name('gestion-academica.cerrar');

    Route::prefix('materias')->name('materias.')->group(function () {
        Route::get('/crear', [MateriaController::class, 'create'])->name('create');
        Route::post('/', [MateriaController::class, 'store'])->name('store');
        Route::get('/{materia}/editar', [MateriaController::class, 'edit'])->name('edit');
        Route::put('/{materia}', [MateriaController::class, 'update'])->name('update');
        Route::patch('/{materia}/estado', [MateriaController::class, 'updateStatus'])->name('status');
    });

    Route::prefix('horarios')->name('horarios.')->group(function () {
        Route::get('/crear', [HorarioController::class, 'create'])->name('create');
        Route::post('/', [HorarioController::class, 'store'])->name('store');
        Route::get('/{horario}/editar', [HorarioController::class, 'edit'])->name('edit');
        Route::put('/{horario}', [HorarioController::class, 'update'])->name('update');
        Route::patch('/{horario}/estado', [HorarioController::class, 'updateStatus'])->name('status');
    });

    Route::prefix('inscripciones')->name('inscripciones.')->group(function () {
        Route::get('/', [InscripcionController::class, 'index'])->name('index');
        Route::get('/crear', [InscripcionController::class, 'create'])->name('create');
        Route::post('/', [InscripcionController::class, 'store'])->name('store');
        Route::patch('/{inscripcion}/estado', [InscripcionController::class, 'updateStatus'])->name('status');
        Route::patch('/{inscripcion}/corregir-notas', [InscripcionController::class, 'corregirNotas'])->name('corregir-notas');
    });
});
Route::middleware(['auth', 'role:administrador'])
    ->prefix('seguimiento-estudiantil')
    ->name('seguimiento-estudiantil.')
    ->group(function () {
        Route::get('/', [SeguimientoEstudiantilController::class, 'index'])->name('index');
        Route::get('/{estudiante}', [SeguimientoEstudiantilController::class, 'show'])->name('show');
        Route::post('/{estudiante}/eventos', [SeguimientoEstudiantilController::class, 'store'])->name('events.store');
    });
Route::middleware(['auth', 'role:docente'])->prefix('panel-docente')->name('panel-docente.')->group(function () {
    Route::get('/estudiantes', [PanelEstudiantilController::class, 'index'])->name('estudiantes');
    Route::get('/academico', [PanelAcademicoController::class, 'index'])->name('academico');
    Route::get('/gestion-academica/{semestre}', [GestionAcademicaController::class, 'semestre'])
        ->name('gestion-academica.semestre');
    Route::get('/gestion-academica/{semestre}/materias/{materia}', [GestionAcademicaController::class, 'materia'])
        ->name('gestion-academica.materia');
    Route::patch('/inscripciones/{inscripcion}/parciales', [InscripcionController::class, 'registerPartial'])
        ->name('inscripciones.register-partial');
    Route::patch('/materias/{materia}/reportar-resultados', [InscripcionController::class, 'reportResults'])
        ->name('inscripciones.report-results');
    Route::get('/seguimiento-estudiantil', [PanelSeguimientoDocenteController::class, 'index'])->name('seguimiento');
    Route::post('/seguimiento-estudiantil/{estudiante}/reportes', [PanelSeguimientoDocenteController::class, 'store'])->name('seguimiento.report');
});
Route::middleware(['auth', 'role:administrador,docente'])
    ->patch('/inscripciones/{inscripcion}/parciales', [InscripcionController::class, 'registerPartial'])
    ->name('inscripciones.register-partial');
Route::middleware(['auth', 'role:administrador'])->prefix('panel/admin')->group(function () {
    Route::get('/', function () {
        return 'Panel de administrador';
    })->name('admin.panel');
});
Route::middleware(['auth', 'role:administrador,docente'])->prefix('panel')->group(function () {
    Route::get('/inicio', function () {
        return 'Panel de administración y docente';
    })->name('panel.inicio');
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/seguridad/preguntas', [SecurityQuestionController::class, 'edit'])
        ->name('security.questions.edit');
    Route::put('/seguridad/preguntas', [SecurityQuestionController::class, 'update'])
        ->name('security.questions.update');
});
Route::middleware(['auth:student', 'role:estudiante,student'])
    ->prefix('estudiante')
    ->name('estudiante.')
    ->group(function () {
        Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::get('/perfil/foto', [ProfileController::class, 'photo'])->name('profile.photo');
        Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/seguridad/preguntas', [SecurityQuestionController::class, 'edit'])
            ->name('security.questions.edit');
        Route::put('/seguridad/preguntas', [SecurityQuestionController::class, 'update'])
            ->name('security.questions.update');
    });
Route::middleware(['auth', 'role:administrador'])->prefix('usuarios')->name('usuarios.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/crear', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/eliminados', [UserController::class, 'deleted'])->name('deleted');
    Route::get('/eliminados/pdf/{filename}', [UserController::class, 'deletedPdf'])->name('deleted.pdf');
    Route::post('/{id}/restaurar', [UserController::class, 'restore'])->name('restore');
    Route::post('/{id}/finalizar-eliminacion', [UserController::class, 'finalizeDeletion'])->name('finalize');
    Route::get('/{id}/foto', [UserController::class, 'photo'])->name('photo');
    Route::get('/{id}', [UserController::class, 'show'])->name('show');
    Route::get('/{id}/exportar-plantilla', [UserController::class, 'exportPdf'])->name('export');
    Route::get('/{id}/datos-personales/editar', [UserController::class, 'editPersonal'])
        ->name('personal.edit');
    Route::patch('/{id}/datos-personales', [UserController::class, 'updatePersonal'])
        ->name('personal.update');
    Route::patch('/{id}/estado', [UserController::class, 'updateStatus'])->name('status');
    Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/recuperar', [UserController::class, 'recover'])->name('recover');
});
Route::middleware(['auth', 'role:administrador'])
    ->prefix('invitados')
    ->name('invitados.')
    ->group(function () {
        Route::get('/', [InvitadoController::class, 'index'])->name('index');
        Route::get('/crear', [InvitadoController::class, 'create'])->name('create');
        Route::post('/', [InvitadoController::class, 'store'])->name('store');
        Route::get('/{invitado}', [InvitadoController::class, 'show'])->name('show');
        Route::get('/{invitado}/editar', [InvitadoController::class, 'edit'])->name('edit');
        Route::put('/{invitado}', [InvitadoController::class, 'update'])->name('update');
        Route::delete('/{invitado}', [InvitadoController::class, 'destroy'])->name('destroy');
    });
Route::middleware(['auth', 'role:administrador,docente'])->group(function () {
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');

    Route::middleware('role:administrador')->group(function () {
        Route::get('/inventario/activos/crear', [ActivoController::class, 'create'])->name('inventario.activos.create');
        Route::post('/inventario/activos', [ActivoController::class, 'store'])->name('inventario.activos.store');
        Route::get('/inventario/activos/{activo}/qr', [ActivoController::class, 'downloadQr'])->name('inventario.activos.qr');
        Route::post('/inventario/activos/{activo}/complementos', [ActivoController::class, 'attachComplemento'])->name('inventario.activos.complementos.attach');
        Route::delete('/inventario/activos/{activo}/complementos/{complemento}', [ActivoController::class, 'detachComplemento'])->name('inventario.activos.complementos.detach');
        Route::get('/inventario/activos/{activo}/editar', [ActivoController::class, 'edit'])->name('inventario.activos.edit');
        Route::put('/inventario/activos/{activo}', [ActivoController::class, 'update'])->name('inventario.activos.update');
        Route::delete('/inventario/activos/{activo}', [ActivoController::class, 'destroy'])->name('inventario.activos.destroy');
        Route::get('/inventario/categorias/crear', [CategoriaController::class, 'create'])->name('inventario.categorias.create');
        Route::post('/inventario/categorias', [CategoriaController::class, 'store'])->name('inventario.categorias.store');
        Route::get('/inventario/categorias/editar', [CategoriaController::class, 'manage'])->name('inventario.categorias.manage');
        Route::get('/inventario/categorias/{categoria}/editar', [CategoriaController::class, 'edit'])->name('inventario.categorias.edit');
        Route::put('/inventario/categorias/{categoria}', [CategoriaController::class, 'update'])->name('inventario.categorias.update');
        Route::delete('/inventario/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('inventario.categorias.destroy');
        Route::get('/equipos/crear', [EquipoController::class, 'create'])->name('equipos.create');
        Route::get('/equipos', [EquipoController::class, 'index'])->name('equipos.index');
        Route::post('/equipos', [EquipoController::class, 'store'])->name('equipos.store');
        Route::get('/equipos/{id}', [EquipoController::class, 'show'])->name('equipos.show');
        Route::get('/equipos/{id}/editar', [EquipoController::class, 'edit'])->name('equipos.edit');
        Route::put('/equipos/{id}', [EquipoController::class, 'update'])->name('equipos.update');
        Route::delete('/equipos/{id}', [EquipoController::class, 'destroy'])->name('equipos.destroy');
    });
    Route::get('/inventario/activos/{activo}', [ActivoController::class, 'show'])->name('inventario.activos.show');
    Route::get('/asignaciones', [AsignacionController::class, 'index'])->name('asignaciones.index');
    Route::get('/asignaciones/crear', [AsignacionController::class, 'create'])->name('asignaciones.create');
    Route::post('/asignaciones', [AsignacionController::class, 'store'])->name('asignaciones.store');
    Route::post('/asignaciones/{id}/cambiar-equipo', [AsignacionController::class, 'cambiarEquipo'])->name('asignaciones.cambiar-equipo');
    Route::put('/asignaciones/{id}/finalizar', [AsignacionController::class, 'finalizar'])
        ->name('asignaciones.finalizar');
    Route::middleware('role:administrador')->group(function () {
        Route::get('/prestamos/crear', [PrestamoController::class, 'create'])->name('prestamos.create');
        Route::post('/prestamos', [PrestamoController::class, 'store'])->name('prestamos.store');
    });
    Route::middleware('role:administrador')->group(function () {
        Route::get('/accesorios', [AccesorioController::class, 'index'])
            ->name('accesorios.index');
        Route::get('/accesorios/crear', [AccesorioController::class, 'create'])
            ->name('accesorios.create');
        Route::post('/accesorios', [AccesorioController::class, 'store'])
            ->name('accesorios.store');
        Route::delete('/accesorios/{id}', [AccesorioController::class, 'destroy'])
            ->name('accesorios.destroy');
    });
    Route::get('/incidencias', [IncidenciaController::class, 'index'])
        ->name('incidencias.index');
    Route::get('/incidencias/crear', [IncidenciaController::class, 'create'])
        ->name('incidencias.create');
    Route::post('/incidencias', [IncidenciaController::class, 'store'])
        ->name('incidencias.store');
    Route::middleware('role:administrador')->group(function () {
        Route::put('/incidencias/{id}/resolver', [IncidenciaController::class, 'resolver'])
            ->name('incidencias.resolver');
        Route::delete('/incidencias/{id}', [IncidenciaController::class, 'destroy'])
            ->name('incidencias.destroy');
    });
    Route::get('/reportes', [ReporteController::class, 'index'])
        ->name('reportes.index');
    Route::get('/reportes/pdf', [ReporteController::class, 'downloadPdf'])
        ->name('reportes.pdf');
});
require __DIR__.'/auth.php';
