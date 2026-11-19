<?php

namespace App\Console\Commands;

use App\Models\Equipo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('equipo:generar-token {equipo_id : ID del equipo registrado} {--dias= : Días de vigencia del token}')]
#[Description('Genera un token único para que un PC o tablet envíe telemetría.')]
class GenerarTokenEquipo extends Command
{
    public function handle(): int
    {
        $equipo = Equipo::query()->find($this->argument('equipo_id'));

        if ($equipo === null) {
            $this->error('No existe el equipo indicado.');

            return self::FAILURE;
        }

        $token = Str::random(64);
        $dias = $this->option('dias');

        $equipo->update([
            'token_hash' => hash('sha256', $token),
            'token_creado_en' => now(),
            'token_expira_en' => $dias !== null ? now()->addDays((int) $dias) : null,
        ]);

        $this->info('Token generado correctamente. Guárdalo: solo se muestra una vez.');
        $this->line($token);

        return self::SUCCESS;
    }
}
