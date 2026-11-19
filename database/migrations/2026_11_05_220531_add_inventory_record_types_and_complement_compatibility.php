<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table): void {
            $table->enum('tipo_registro', ['activo', 'equipo', 'complemento'])
                ->default('activo')
                ->after('grupo');
        });

        DB::table('categorias')
            ->where('grupo', 'equipo')
            ->update(['tipo_registro' => 'equipo']);
        DB::table('categorias')
            ->where('grupo', 'complemento')
            ->update(['tipo_registro' => 'complemento']);

        Schema::table('equipos', function (Blueprint $table): void {
            $table->enum('tipo_equipo', ['cpu', 'tablet', 'mini_pc', 'router', 'wifi'])
                ->default('cpu')
                ->change();
        });

        Schema::table('complementos', function (Blueprint $table): void {
            $table->json('tipos_equipo_compatibles')->nullable()->after('tipo_complemento');
        });

        DB::table('complementos')
            ->select(['id', 'tipo_complemento'])
            ->orderBy('id')
            ->each(function (object $complemento): void {
                $tipo = Str::lower($complemento->tipo_complemento);
                $tiposCompatibles = match (true) {
                    Str::contains($tipo, ['monitor', 'pantalla']) => ['cpu', 'mini_pc'],
                    Str::contains($tipo, ['apoyador', 'soporte']) => ['tablet'],
                    Str::contains($tipo, ['teclado', 'mouse', 'raton', 'ratón']) => ['cpu', 'tablet', 'mini_pc'],
                    default => ['cpu', 'tablet', 'mini_pc', 'router', 'wifi'],
                };

                DB::table('complementos')
                    ->where('id', $complemento->id)
                    ->update(['tipos_equipo_compatibles' => json_encode($tiposCompatibles, JSON_THROW_ON_ERROR)]);
            });
    }

    public function down(): void
    {
        if (DB::table('equipos')->whereIn('tipo_equipo', ['mini_pc', 'router', 'wifi'])->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan equipos mini PC o de red registrados.');
        }

        Schema::table('complementos', function (Blueprint $table): void {
            $table->dropColumn('tipos_equipo_compatibles');
        });

        Schema::table('equipos', function (Blueprint $table): void {
            $table->enum('tipo_equipo', ['cpu', 'tablet'])->default('cpu')->change();
        });

        Schema::table('categorias', function (Blueprint $table): void {
            $table->dropColumn('tipo_registro');
        });
    }
};
