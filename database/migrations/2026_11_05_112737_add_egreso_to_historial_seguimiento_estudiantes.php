<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE historial_seguimiento_estudiantes MODIFY tipo_evento ENUM('congelamiento', 'retiro', 'reincorporacion', 'observacion', 'abandono', 'egreso') NOT NULL");
    }

    public function down(): void
    {
        if (DB::table('historial_seguimiento_estudiantes')->where('tipo_evento', 'egreso')->exists()) {
            throw new RuntimeException(
                'Student graduation events exist; preserve them before rolling back this migration.',
            );
        }

        DB::statement("ALTER TABLE historial_seguimiento_estudiantes MODIFY tipo_evento ENUM('congelamiento', 'retiro', 'reincorporacion', 'observacion', 'abandono') NOT NULL");
    }
};
