<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Equipo extends Model
{
    use HasFactory;

    protected $table = 'equipos';

    protected $fillable = [
        'activo_id',
        'identificador',
        'tipo_equipo',
        'modo_uso',
        'mac',
        'es_servidor',
        'token_hash',
        'token_creado_en',
        'token_expira_en',
        'codigo_vinculacion_hash',
        'codigo_vinculacion_expira_en',
        'ultima_conexion',
        'ip_ultima',
    ];

    protected function casts(): array
    {
        return [
            'es_servidor' => 'boolean',
            'token_creado_en' => 'datetime',
            'token_expira_en' => 'datetime',
            'codigo_vinculacion_expira_en' => 'datetime',
            'ultima_conexion' => 'datetime',
        ];
    }

    public function scopeAuthorizedByDeviceToken(Builder $query, string $token): Builder
    {
        return $query
            ->where('token_hash', hash('sha256', $token))
            ->where(function (Builder $query): void {
                $query->whereNull('token_expira_en')
                    ->orWhere('token_expira_en', '>', now());
            });
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(Heartbeat::class);
    }

    public function ultimoHeartbeat(): HasOne
    {
        return $this->hasOne(Heartbeat::class)->latestOfMany('fecha_hora');
    }

    public function diagnosticos(): HasMany
    {
        return $this->hasMany(Diagnostico::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }

    public function complementos(): BelongsToMany
    {
        return $this->belongsToMany(Complemento::class, 'equipo_complemento')
            ->withPivot(['fecha_inicio', 'fecha_fin', 'observaciones'])
            ->withTimestamps();
    }
}
