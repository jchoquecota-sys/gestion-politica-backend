<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Persona extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'dni',
        'nombres',
        'apellidos',
        'celular',
        'email',
        'direccion',
        'fecha_nacimiento',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function sectorPersonas(): HasMany
    {
        return $this->hasMany(SectorPersona::class);
    }

    public function basePersonas(): HasMany
    {
        return $this->hasMany(BasePersona::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }
}
