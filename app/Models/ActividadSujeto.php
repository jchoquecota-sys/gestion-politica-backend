<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActividadSujeto extends Model
{
    use SoftDeletes;

    protected $table = 'actividad_sujetos';

    protected $fillable = [
        'actividad_id',
        'sujeto_id',
        'sujeto_type',
        'descripcion_ejecucion',
        'evidencias',
        'hora_asistencia',
        'metodo_registro',
        'registrado_por',
        'latitud_capturada',
        'longitud_capturada',
        'device_fingerprint',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'evidencias' => 'json',
        'hora_asistencia' => 'datetime',
        'latitud_capturada' => 'decimal:8',
        'longitud_capturada' => 'decimal:8',
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'actividad_id');
    }

    /**
     * Get the parent sujeto model (Persona, Base, or Sector).
     */
    public function sujeto(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function deleter(): BelongsTo { return $this->belongsTo(User::class, 'deleted_by'); }
}
