<?php

namespace App\Http\Requests\Actividad;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActividadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo'            => 'sometimes|required|string|max:255',
            'descripcion'       => 'nullable|string',
            'fecha_actividad'   => 'sometimes|required|date_format:Y-m-d H:i:s',
            'tipo_actividad_id' => 'sometimes|required|exists:tipos_actividad,id',
            'estado'            => 'sometimes|required|in:borrador,creada,cancelada',
            'es_publica'        => 'nullable|boolean',
            'foto_portada'      => 'nullable|image|max:5120', // Max 5MB

            // Sujetos vinculados (opcional en update, si se envía se reemplazan)
            'sujetos'                        => 'nullable|array',
            'sujetos.*.sujeto_id'            => 'required|integer',
            'sujetos.*.sujeto_type'          => 'required|in:persona,base,sector',
            'sujetos.*.descripcion_ejecucion' => 'nullable|string',
            'sujetos.*.evidencias'           => 'nullable|array',
        ];
    }
}
