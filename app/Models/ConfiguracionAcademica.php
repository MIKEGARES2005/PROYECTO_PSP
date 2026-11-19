<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionAcademica extends Model
{
    protected $table = 'configuracion_academica';

    protected $fillable = [
        'gestion_vigente',
    ];

    /**
     * Gestión académica vigente para todas las inscripciones nuevas.
     */
    public static function gestionVigente(): ?string
    {
        $configuracion = static::query()->find(1);
        $gestionActiva = Gestion::activa();

        if ($configuracion?->gestion_vigente === null || $gestionActiva === null) {
            return null;
        }

        $codigoConfigurado = self::normalizarCodigo($configuracion->gestion_vigente);

        return $gestionActiva->codigo === $codigoConfigurado ? $codigoConfigurado : null;
    }

    public static function actualizarGestionVigente(string $codigo): self
    {
        return static::query()->updateOrCreate(
            ['id' => 1],
            ['gestion_vigente' => self::normalizarCodigo($codigo)],
        );
    }

    public static function normalizarCodigo(string $codigo): string
    {
        if (preg_match('/^(\\d{4})-(I|II)$/', $codigo, $coincidencias) === 1) {
            return $coincidencias[2].'-'.$coincidencias[1];
        }

        return strtoupper(trim($codigo));
    }
}
