<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingSetting extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'landing_settings';

    protected $fillable = [
        'nombre_candidato',
        'cargo_candidatura',
        'eslogan',
        'biografia',
        'logo_path',
        'foto_principal_path',
        'foto_secundaria_path',
        'redes_sociales',
        'color_primario',
        'color_secundario',
        'meta_titulo',
        'meta_descripcion',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'redes_sociales' => 'array',
    ];

    // creator(), updater(), deleter() are provided by HasAuditFields trait

    /**
     * Obtener la única instancia de configuración de la landing page.
     * Si no existe, devuelve un modelo vacío con defaults razonables.
     */
    public static function getActive(): static
    {
        return static::latest()->firstOrNew([]);
    }
}
