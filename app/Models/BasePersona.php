<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasePersona extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'base_personas';
    public $incrementing = true;

    protected $fillable = [
        'base_id',
        'persona_id',
        'cargo_id',
        'es_principal',
        'fecha_inicio',
        'observaciones',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at'
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'fecha_inicio' => 'date:Y-m-d',
    ];

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }
}
