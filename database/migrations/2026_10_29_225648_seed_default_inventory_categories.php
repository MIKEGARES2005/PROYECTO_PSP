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
        $ahora = now();

        DB::table('categorias')->insertOrIgnore([
            ['nombre' => 'Equipos', 'grupo' => 'equipo', 'estado' => 'activa', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Complementos', 'grupo' => 'electronico', 'estado' => 'activa', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Muebles', 'grupo' => 'mobiliario', 'estado' => 'activa', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('categorias')->whereIn('nombre', ['Equipos', 'Complementos', 'Muebles'])->delete();
    }
};
