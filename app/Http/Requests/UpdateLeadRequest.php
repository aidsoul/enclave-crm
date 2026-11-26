<?php

namespace App\Http\Requests;

class UpdateLeadRequest extends ApiFormRequest
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
            'status' => 'nullable|string|in:new,in_progress,converted,rejected',
            'manager_id' => 'nullable|integer|exists:users,id',
            'message' => 'nullable|string|max:2000',
        ];
    }
}
