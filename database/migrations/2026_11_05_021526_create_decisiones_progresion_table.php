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
        Schema::create('decisiones_progresion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gestion_id')->constrained('gestiones')->restrictOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->restrictOnDelete();
            $table->string('decision_recomendada', 40);
            $table->string('decision_confirmada', 40)->nullable();
            $table->text('motivo')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamps();

            $table->unique(['gestion_id', 'estudiante_id'], 'decisiones_progresion_gestion_estudiante_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('decisiones_progresion')
            && DB::table('decisiones_progresion')->exists()) {
            throw new RuntimeException(
                'Academic progression decisions exist; preserve them before rolling back this migration.',
            );
        }

        Schema::dropIfExists('decisiones_progresion');
    }
};
