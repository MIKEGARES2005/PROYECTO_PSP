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
            $table->string('prefijo_codigo', 8)->nullable()->unique();
            $table->unsignedBigInteger('codigo_secuencia')->default(0);
        });

        $prefijosPorCategoria = [];
        $prefijosUtilizados = [];
        $categorias = DB::table('categorias')->orderBy('id')->get(['id', 'nombre']);

        foreach ($categorias as $categoria) {
            $prefijo = $this->prefijoParaCategoria($categoria->nombre, $prefijosUtilizados);
            $prefijosUtilizados[] = $prefijo;
            $prefijosPorCategoria[$categoria->id] = $prefijo;
            DB::table('categorias')->where('id', $categoria->id)->update(['prefijo_codigo' => $prefijo]);
        }

        Schema::table('activos', function (Blueprint $table): void {
            $table->dropUnique('activos_codigo_unique');
        });

        foreach ($categorias as $categoria) {
            $correlativo = 0;
            $activos = DB::table('activos')
                ->where('categoria_id', $categoria->id)
                ->orderBy('id')
                ->get(['id']);

            foreach ($activos as $activo) {
                $correlativo++;
                DB::table('activos')
                    ->where('id', $activo->id)
                    ->update(['codigo' => $this->codigo($prefijosPorCategoria[$categoria->id], $correlativo)]);
            }

            DB::table('categorias')
                ->where('id', $categoria->id)
                ->update(['codigo_secuencia' => $correlativo]);
        }

        Schema::table('activos', function (Blueprint $table): void {
            $table->unique('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table): void {
            $table->dropUnique('categorias_prefijo_codigo_unique');
            $table->dropColumn(['prefijo_codigo', 'codigo_secuencia']);
        });
    }

    /**
     * @param  list<string>  $prefijosUtilizados
     */
    private function prefijoParaCategoria(string $nombre, array $prefijosUtilizados): string
    {
        $normalizado = Str::lower(Str::ascii($nombre));
        $prefijosConocidos = [
            'equipos' => 'EQP',
            'complementos' => 'COM',
            'muebles' => 'MOB',
            'sillas' => 'SILLA',
            'mesas' => 'MESA',
            'pc' => 'PC',
            'computadoras' => 'PC',
            'tablets' => 'TAB',
            'tablet' => 'TAB',
            'tv' => 'TV',
            'televisores' => 'TV',
            'televisor' => 'TV',
            'teclados' => 'TEC',
            'mouse' => 'MOU',
            'monitores' => 'MON',
            'monitor' => 'MON',
        ];
        $base = $prefijosConocidos[$normalizado] ?? $this->prefijoDesdeNombre($nombre);
        $prefijo = $base;
        $sufijo = 1;

        while (in_array($prefijo, $prefijosUtilizados, true)) {
            $marca = (string) $sufijo++;
            $prefijo = substr($base, 0, 8 - strlen($marca)).$marca;
        }

        return $prefijo;
    }

    private function prefijoDesdeNombre(string $nombre): string
    {
        $palabras = preg_split('/[^A-Za-z0-9]+/', Str::ascii($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $iniciales = implode('', array_map(fn (string $palabra): string => substr($palabra, 0, 1), $palabras));
        $prefijo = strtoupper(substr(count($palabras) > 1 ? $iniciales : ($palabras[0] ?? 'CAT'), 0, 8));

        return strlen($prefijo) >= 2 ? $prefijo : 'CAT';
    }

    private function codigo(string $prefijo, int $correlativo): string
    {
        return $prefijo.'-'.str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT);
    }
};
