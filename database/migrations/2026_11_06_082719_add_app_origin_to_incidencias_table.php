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
        DB::statement("ALTER TABLE incidencias MODIFY origen ENUM('manual', 'automatica', 'estudiante', 'app') NOT NULL DEFAULT 'manual'");
        DB::statement('ALTER TABLE incidencias DROP CONSTRAINT chk_incidencias_reportante');
        DB::statement("
            ALTER TABLE incidencias
            ADD CONSTRAINT chk_incidencias_reportante
            CHECK (origen IN ('automatica', 'app') OR reportado_por IS NOT NULL)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('incidencias')->where('origen', 'app')->update(['origen' => 'automatica']);
        DB::statement('ALTER TABLE incidencias DROP CONSTRAINT chk_incidencias_reportante');
        DB::statement("ALTER TABLE incidencias MODIFY origen ENUM('manual', 'automatica', 'estudiante') NOT NULL DEFAULT 'manual'");
        DB::statement("
            ALTER TABLE incidencias
            ADD CONSTRAINT chk_incidencias_reportante
            CHECK (origen = 'automatica' OR reportado_por IS NOT NULL)
        ");
    }
};
