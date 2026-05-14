<?php

namespace App\Http\Requests\Sector;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('personas:edit');
    }

    public function rules(): array
    {
        $personaId = $this->route('persona') ? $this->route('persona')->id : null;
        
        return [
            'nombres'          => 'required|string|max:255',
            'apellidos'        => 'required|string|max:255',
            'dni'              => "nullable|string|max:8|unique:personas,dni,{$personaId}",
            'celular'          => 'nullable|string|max:15',
            'email'            => "nullable|email|unique:personas,email,{$personaId}",
            'direccion'        => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date',
            'foto'             => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
}
