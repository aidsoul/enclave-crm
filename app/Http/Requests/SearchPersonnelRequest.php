<?php

namespace App\Http\Requests;

class SearchPersonnelRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => 'required|string|in:manager,courier',
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }
}
