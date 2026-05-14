<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Actividad extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'actividades';

    protected $fillable = [
        'titulo',
        'descripcion',
        'fecha_actividad',
        'tipo_actividad_id',
        'estado',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'fecha_actividad' => 'datetime',
    ];

    public function tipoActividad(): BelongsTo
    {
        return $this->belongsTo(TipoActividad::class, 'tipo_actividad_id');
    }

    public function sujetos(): HasMany
    {
        return $this->hasMany(ActividadSujeto::class, 'actividad_id');
    }

    // creator(), updater(), deleter() are provided by HasAuditFields trait
}
