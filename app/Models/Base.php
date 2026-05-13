<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Base extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'sector_id',
        'nombre',
        'descripcion',
        'direccion',
        'latitud',
        'longitud',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
    ];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function basePersonas(): HasMany
    {
        return $this->hasMany(BasePersona::class);
    }

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'base_personas')
            ->withPivot(['id', 'cargo_id', 'es_principal', 'fecha_inicio', 'observaciones'])
            ->withTimestamps()
            ->whereNull('base_personas.deleted_at');
    }
}
