<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoActividad extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'tipos_actividad';

    protected $fillable = [
        'nombre',
        'descripcion',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'tipo_actividad_id');
    }

    // creator(), updater(), deleter() are provided by HasAuditFields trait
}
