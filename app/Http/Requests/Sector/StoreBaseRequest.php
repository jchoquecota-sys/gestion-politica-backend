<?php

namespace App\Http\Requests\Sector;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sector_id' => 'required|exists:sectores,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'direccion' => 'nullable|string|max:255',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            
            // Opcional: Vincular personas al crear
            'personas' => 'nullable|array',
            'personas.*.persona_id' => 'required|exists:personas,id',
            'personas.*.cargo_id' => 'required|exists:cargos,id',
            'personas.*.es_principal' => 'nullable|boolean',
            'personas.*.fecha_inicio' => 'nullable|date',
            'personas.*.observaciones' => 'nullable|string',
        ];
    }
}
