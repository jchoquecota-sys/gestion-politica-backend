<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by the route middleware.
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:roles,name',
                // Prevent overwriting the protected system role.
                'not_in:super-admin',
            ],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.not_in' => 'El nombre "super-admin" está reservado para el sistema.',
        ];
    }
}
