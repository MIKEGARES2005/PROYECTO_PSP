<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE categorias
            MODIFY grupo ENUM('mobiliario', 'red', 'electronico', 'equipo', 'otro', 'complemento') NOT NULL
        ");

        DB::table('categorias')
            ->where(function (Builder $query): void {
                $query->whereIn('id', DB::table('activos')
                    ->join('complementos', 'complementos.activo_id', '=', 'activos.id')
                    ->select('activos.categoria_id'))
                    ->orWhere(function (Builder $categoryQuery): void {
                        $categoryQuery->where('nombre', 'Complementos')
                            ->where('grupo', 'electronico');
                    });
            })
            ->update(['grupo' => 'complemento']);
    }

    public function down(): void
    {
        DB::table('categorias')
            ->where('grupo', 'complemento')
            ->update(['grupo' => 'electronico']);

        DB::statement("
            ALTER TABLE categorias
            MODIFY grupo ENUM('mobiliario', 'red', 'electronico', 'equipo', 'otro') NOT NULL
        ");
    }
};
