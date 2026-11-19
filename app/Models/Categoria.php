<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = [
        'nombre',
        'grupo',
        'tipo_registro',
        'estado',
        'prefijo_codigo',
        'codigo_secuencia',
    ];

    protected function casts(): array
    {
        return [
            'codigo_secuencia' => 'integer',
        ];
    }

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class);
    }
}
