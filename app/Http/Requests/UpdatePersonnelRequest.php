<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdatePersonnelRequest extends PersonnelBaseRequest
{
    public function rules(): array
    {
        return [
            'system_name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['sometimes', 'string', Rule::in(['manager', 'courier'])],
        ];
    }
}
