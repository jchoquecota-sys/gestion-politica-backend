<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cargo extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'nombre',
        'descripcion',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function sectorPersonas(): HasMany
    {
        return $this->hasMany(SectorPersona::class);
    }
}
