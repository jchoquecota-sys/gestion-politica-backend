<?php

namespace App\Http\Requests\Actividad;

use Illuminate\Foundation\Http\FormRequest;

class StoreActividadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_actividad' => 'required|date_format:Y-m-d H:i:s',
            'tipo_actividad_id' => 'required|exists:tipos_actividad,id',
            'estado' => 'required|in:borrador,creada,cancelada',
            
            // Sujetos vinculados
            'sujetos' => 'required|array|min:1',
            'sujetos.*.sujeto_id' => 'required|integer',
            'sujetos.*.sujeto_type' => 'required|in:persona,base,sector',
            'sujetos.*.descripcion_ejecucion' => 'nullable|string',
            'sujetos.*.evidencias' => 'nullable|array',
        ];
    }
}
