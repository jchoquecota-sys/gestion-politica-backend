<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sector extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'sectores';

    protected $fillable = [
        'nombre',
        'descripcion',
        'codigo',
        'referencia_ubicacion',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'sector_personas')
            ->withPivot(['id', 'cargo_id', 'es_principal', 'fecha_inicio', 'fecha_fin', 'observaciones'])
            ->withTimestamps();
    }

    public function sectorPersonas(): HasMany
    {
        return $this->hasMany(SectorPersona::class);
    }

    /**
     * Obtener el encargado principal del sector
     */
    public function responsablePrincipal()
    {
        return $this->sectorPersonas()
            ->where('es_principal', true)
            ->with(['persona', 'cargo'])
            ->first();
    }
}
