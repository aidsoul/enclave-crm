<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_courier_id' => 'required|exists:users,id',
        ];
    }
}
