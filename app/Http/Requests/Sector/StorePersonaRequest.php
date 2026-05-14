<?php

namespace App\Http\Requests\Sector;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('personas:create');
    }

    public function rules(): array
    {
        return [
            'nombres'          => 'required|string|max:255',
            'apellidos'        => 'required|string|max:255',
            'dni'              => 'nullable|string|max:8|unique:personas,dni',
            'celular'          => 'nullable|string|max:15',
            'email'            => 'nullable|email|unique:personas,email',
            'direccion'        => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date',
            'foto'             => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
}
