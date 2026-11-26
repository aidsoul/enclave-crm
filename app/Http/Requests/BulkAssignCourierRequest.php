<?php

namespace App\Http\Requests;

class BulkAssignCourierRequest extends ApiFormRequest
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
            'order_ids' => 'required|array|min:1|max:100',
            'order_ids.*' => 'required|integer|exists:orders,id',
            'delivery_courier_id' => 'required|integer|exists:users,id',
        ];
    }
}
