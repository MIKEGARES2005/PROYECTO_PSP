<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Complemento extends Model
{
    use HasFactory;

    protected $table = 'complementos';

    protected $fillable = [
        'activo_id',
        'tipo_complemento',
        'tipos_equipo_compatibles',
        'estado_fisico',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'tipos_equipo_compatibles' => 'array',
        ];
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'equipo_complemento')
            ->withPivot(['fecha_inicio', 'fecha_fin', 'observaciones'])
            ->withTimestamps();
    }
}
