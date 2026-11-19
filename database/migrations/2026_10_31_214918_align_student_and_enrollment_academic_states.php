<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $inscripcionesInactivas = DB::table('inscripciones')->where('estado', 'Inactivo')->count();

        if ($inscripcionesInactivas > 0) {
            throw new RuntimeException(
                "Se encontraron {$inscripcionesInactivas} inscripciones Inactivo. Revísalas y clasifícalas antes de normalizar los estados.",
            );
        }

        $estudiantesEgresados = DB::table('estudiantes')->where('estado', 'egresado')->count();

        if ($estudiantesEgresados > 0) {
            throw new RuntimeException(
                "Se encontraron {$estudiantesEgresados} estudiantes egresados. Verifica sus materias de cuarto semestre antes de definirlos como retirados.",
            );
        }

        Schema::table('estudiantes', function (Blueprint $table): void {
            $table->enum('estado', ['activo', 'inactivo', 'retirado', 'congelado'])
                ->default('activo')
                ->change();
        });

        Schema::table('inscripciones', function (Blueprint $table): void {
            $table->enum('estado', ['Cursando', 'Aprobado', 'Reprobado'])
                ->default('Cursando')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Academic state normalization is not reversible; restore a backup before rolling back this migration.',
        );
    }
};
