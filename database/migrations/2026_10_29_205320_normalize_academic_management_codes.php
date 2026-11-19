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
        foreach (['gestiones' => 'codigo', 'configuracion_academica' => 'gestion_vigente', 'inscripciones' => 'gestion', 'horarios' => 'gestion'] as $table => $column) {
            DB::statement("UPDATE {$table} SET {$column} = CONCAT(SUBSTRING_INDEX({$column}, '-', -1), '-', LEFT({$column}, 4)) WHERE {$column} REGEXP '^[0-9]{4}-(I|II)$'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['gestiones' => 'codigo', 'configuracion_academica' => 'gestion_vigente', 'inscripciones' => 'gestion', 'horarios' => 'gestion'] as $table => $column) {
            DB::statement("UPDATE {$table} SET {$column} = CONCAT(SUBSTRING_INDEX({$column}, '-', -1), '-', LEFT({$column}, 4)) WHERE {$column} REGEXP '^(I|II)-[0-9]{4}$'");
        }
    }
};
