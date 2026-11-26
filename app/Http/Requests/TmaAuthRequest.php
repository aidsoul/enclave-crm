<?php

namespace App\Http\Requests;

class TmaAuthRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'initData' => 'required|string',
            'session_token' => 'nullable|string',
        ];
    }
}
