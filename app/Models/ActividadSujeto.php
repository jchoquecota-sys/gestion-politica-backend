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
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'evidencias' => 'json',
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
