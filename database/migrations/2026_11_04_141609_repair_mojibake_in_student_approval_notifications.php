<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notificaciones')
            ->where('tipo', 'solicitud_aprobada')
            ->where(
                'mensaje',
                'Tu solicitud fue aceptada. Al iniciar sesiÃ³n, cambia tu usuario y contraseÃ±a y registra tus preguntas de seguridad en el panel de tu perfil.'
            )
            ->update([
                'mensaje' => 'Tu solicitud fue aceptada. Al iniciar sesión, cambia tu usuario y contraseña y registra tus preguntas de seguridad en el panel de tu perfil.',
            ]);
    }

    public function down(): void
    {
        // The text repair is intentionally irreversible.
    }
};
