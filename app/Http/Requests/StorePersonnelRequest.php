<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StorePersonnelRequest extends PersonnelBaseRequest
{
    public function rules(): array
    {
        return [
            'system_name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(['manager', 'courier'])],
            'telegram_user_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
