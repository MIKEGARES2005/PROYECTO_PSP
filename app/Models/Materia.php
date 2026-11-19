<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Materia extends Model
{
    use HasFactory;

    protected $table = 'materias';

    public const SEMESTRES = [
        1 => 'Primero',
        2 => 'Segundo',
        3 => 'Tercero',
        4 => 'Cuarto',
    ];

    protected $fillable = [
        'nombre',
        'sigla',
        'semestre',
        'estado',
    ];

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function asignaciones(): HasManyThrough
    {
        return $this->hasManyThrough(Asignacion::class, Horario::class);
    }

    public static function nombreSemestre(string|int|null $semestre): ?string
    {
        $valor = trim((string) $semestre);

        if ($valor === '') {
            return null;
        }

        if (isset(self::SEMESTRES[(int) $valor]) && ctype_digit($valor)) {
            return self::SEMESTRES[(int) $valor];
        }

        foreach (self::SEMESTRES as $nombre) {
            if (mb_strtolower($nombre) === mb_strtolower($valor)) {
                return $nombre;
            }
        }

        return null;
    }

    public function getSemestreNombreAttribute(): string
    {
        return self::nombreSemestre($this->semestre) ?? (string) $this->semestre;
    }
}
