<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categoriaAnteriorExiste = DB::table('categorias')
            ->where('nombre', 'Complementos')
            ->exists();
        $categoriaNuevaExiste = DB::table('categorias')
            ->where('nombre', 'Periféricos y complementos')
            ->exists();

        if ($categoriaAnteriorExiste && $categoriaNuevaExiste) {
            throw new RuntimeException('No se puede renombrar la categoría porque ya existe "Periféricos y complementos".');
        }

        DB::table('categorias')
            ->where('nombre', 'Complementos')
            ->update(['nombre' => 'Periféricos y complementos']);
    }

    public function down(): void
    {
        $categoriaAnteriorExiste = DB::table('categorias')
            ->where('nombre', 'Complementos')
            ->exists();
        $categoriaNuevaExiste = DB::table('categorias')
            ->where('nombre', 'Periféricos y complementos')
            ->exists();

        if ($categoriaAnteriorExiste && $categoriaNuevaExiste) {
            throw new RuntimeException('No se puede revertir el nombre porque ya existe la categoría "Complementos".');
        }

        DB::table('categorias')
            ->where('nombre', 'Periféricos y complementos')
            ->update(['nombre' => 'Complementos']);
    }
};
