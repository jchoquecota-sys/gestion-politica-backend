<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by the route middleware.
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($this->route('role')),
                function ($attribute, $value, $fail) {
                    if ($value === 'super-admin' && $this->route('role')->name !== 'super-admin') {
                        $fail('El nombre "super-admin" está reservado para el sistema.');
                    }
                },
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
