<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE estudiantes MODIFY estado ENUM('activo', 'inactivo', 'retirado', 'congelado', 'egresado') NOT NULL DEFAULT 'activo'");
    }

    public function down(): void
    {
        if (DB::table('estudiantes')->where('estado', 'egresado')->exists()) {
            throw new RuntimeException(
                'Graduated students exist; preserve their states before rolling back this migration.',
            );
        }

        DB::statement("ALTER TABLE estudiantes MODIFY estado ENUM('activo', 'inactivo', 'retirado', 'congelado') NOT NULL DEFAULT 'activo'");
    }
};
