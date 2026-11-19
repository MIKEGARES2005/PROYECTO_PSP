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
        Schema::create('inscripciones_archivadas', function (Blueprint $table): void {
            $table->id();
            $table->string('gestion', 7);
            $table->unsignedBigInteger('inscripcion_original_id');
            $table->unsignedBigInteger('estudiante_id');
            $table->string('estudiante_nombre', 300);
            $table->string('matricula', 30)->nullable();
            $table->unsignedBigInteger('materia_id');
            $table->string('materia_nombre', 150);
            $table->string('semestre', 20);
            $table->string('tipo', 20);
            $table->string('resultado_final', 20);
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->string('decision_progresion', 40);
            $table->json('datos_inscripcion');
            $table->timestamp('archivado_en');
            $table->timestamps();

            $table->unique(
                ['gestion', 'inscripcion_original_id'],
                'inscripciones_archivadas_gestion_origen_unique',
            );
            $table->index(['gestion', 'estudiante_id']);
        });

        $configuracion = DB::table('configuracion_academica')->where('id', 1)->first();

        if ($configuracion === null) {
            return;
        }

        $gestionActiva = DB::table('gestiones')->where('activa', true)->first();

        if ($gestionActiva !== null) {
            DB::table('configuracion_academica')->where('id', 1)->update([
                'gestion_vigente' => $gestionActiva->codigo,
            ]);

            return;
        }

        $codigo = strtoupper(trim((string) $configuracion->gestion_vigente));

        if (preg_match('/^(I|II)-(\d{4})$/', $codigo, $matches) !== 1) {
            if (preg_match('/^(\d{4})-(I|II)$/', $codigo, $matches) !== 1) {
                return;
            }

            $codigo = $matches[2].'-'.$matches[1];
            $periodo = $matches[2];
            $anio = (int) $matches[1];
        } else {
            $periodo = $matches[1];
            $anio = (int) $matches[2];
        }

        $periodoNumero = $periodo === 'I' ? '1' : '2';
        $fechaInicio = sprintf('%04d-%02d-01', $anio, $periodo === 'I' ? 2 : 8);
        $fechaFin = $periodo === 'I'
            ? sprintf('%04d-07-31', $anio)
            : sprintf('%04d-11-30', $anio);

        DB::table('gestiones')->update(['activa' => false]);
        DB::table('gestiones')->updateOrInsert(
            ['codigo' => $codigo],
            [
                'anio' => $anio,
                'periodo' => $periodoNumero,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'activa' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        DB::table('configuracion_academica')->where('id', 1)->update([
            'gestion_vigente' => $codigo,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('inscripciones_archivadas')
            && DB::table('inscripciones_archivadas')->exists()) {
            throw new RuntimeException(
                'Academic enrollment archives exist; preserve them before rolling back this migration.',
            );
        }
        Schema::dropIfExists('inscripciones_archivadas');
        Schema::dropIfExists('inscripciones_archivadas');
    }
};
