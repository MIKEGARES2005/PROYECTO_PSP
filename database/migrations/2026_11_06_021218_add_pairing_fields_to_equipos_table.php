<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table): void {
            $table->char('codigo_vinculacion_hash', 64)->nullable()->unique();
            $table->timestamp('codigo_vinculacion_expira_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table): void {
            $table->dropUnique(['codigo_vinculacion_hash']);
            $table->dropColumn(['codigo_vinculacion_hash', 'codigo_vinculacion_expira_en']);
        });
    }
};
