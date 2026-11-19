<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $configuracion = DB::table('configuracion_academica')->where('id', 1)->first();
        $codigoConfigurado = strtoupper(trim((string) ($configuracion->gestion_vigente ?? '')));

        if (preg_match('/^(\d{4})-(I|II)$/', $codigoConfigurado, $matches) === 1) {
            $codigoConfigurado = $matches[2].'-'.$matches[1];
        }

        $gestiones = [
            [
                'codigo' => 'I-2026',
                'anio' => 2026,
                'periodo' => '1',
                'fecha_inicio' => '2026-02-01',
                'fecha_fin' => '2026-07-31',
                'activa' => false,
            ],
            [
                'codigo' => 'II-2026',
                'anio' => 2026,
                'periodo' => '2',
                'fecha_inicio' => '2026-08-01',
                'fecha_fin' => '2026-11-30',
                'activa' => false,
            ],
        ];

        foreach ($gestiones as $gestion) {
            if (DB::table('gestiones')->where('codigo', $gestion['codigo'])->exists()) {
                continue;
            }

            $activarGestion = $gestion['codigo'] === 'II-2026'
                && $codigoConfigurado === 'II-2026'
                && ! DB::table('gestiones')->where('activa', true)->exists();

            DB::table('gestiones')->insert([
                ...$gestion,
                'activa' => $activarGestion,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Initial academic periods may contain operational history; preserve the records before rolling back this migration.',
        );
    }
};
