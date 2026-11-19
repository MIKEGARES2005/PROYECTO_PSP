<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Estudiante extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'matricula',
        'semestre',
        'generacion_ingreso',
        'ci',
        'ci_extension',
        'telefono',
        'correo_institucional',
        'correo_personal',
        'direccion',
        'foto_personal',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'estudiante_solicitante_id');
    }

    public function solicitudIntegrantes(): HasMany
    {
        return $this->hasMany(SolicitudIntegrante::class, 'estudiante_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function historialSeguimiento(): HasMany
    {
        return $this->hasMany(HistorialSeguimientoEstudiante::class);
    }

    public function aproboTodasLasMateriasDeCuarto(): bool
    {
        $materiasCuarto = Materia::query()
            ->whereIn('semestre', ['Cuarto', 'cuarto', '4'])
            ->where('estado', 'activo')
            ->pluck('id');

        if ($materiasCuarto->isEmpty()) {
            return false;
        }

        $materiasAprobadas = $this->inscripciones()
            ->whereIn('materia_id', $materiasCuarto)
            ->where('estado', 'Aprobado')
            ->where('resultado_final', 'Aprobado')
            ->lockForUpdate()
            ->pluck('materia_id')
            ->merge(
                DB::table('inscripciones_archivadas')
                    ->where('estudiante_id', $this->id)
                    ->whereIn('materia_id', $materiasCuarto)
                    ->where('resultado_final', 'Aprobado')
                    ->pluck('materia_id'),
            )
            ->unique();

        return $materiasCuarto->diff($materiasAprobadas)->isEmpty();
    }

    public function nombreCompleto(): string
    {
        $parts = array_filter([
            $this->nombres,
            $this->apellido_paterno,
            $this->apellido_materno,
        ]);

        return trim(implode(' ', $parts));
    }

    public function getSemestreNombreAttribute(): string
    {
        return Materia::nombreSemestre($this->semestre) ?? (string) $this->semestre;
    }
}
