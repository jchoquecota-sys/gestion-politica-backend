<?php

namespace App\Http\Requests\Landing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_candidato'  => 'sometimes|string|max:150',
            'cargo_candidatura' => 'nullable|string|max:150',
            'eslogan'           => 'nullable|string|max:255',
            'biografia'         => 'nullable|string',
            'logo'              => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'foto_principal'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'foto_secundaria'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'redes_sociales'    => 'nullable|array',
            'redes_sociales.facebook'  => 'nullable|url',
            'redes_sociales.instagram' => 'nullable|url',
            'redes_sociales.tiktok'    => 'nullable|url',
            'redes_sociales.twitter'   => 'nullable|url',
            'redes_sociales.whatsapp'  => 'nullable|string|max:20',
            'color_primario'    => 'nullable|string|max:20',
            'color_secundario'  => 'nullable|string|max:20',
            'meta_titulo'       => 'nullable|string|max:120',
            'meta_descripcion'  => 'nullable|string|max:300',
        ];
    }
}
