<?php

namespace App\Http\Requests;

use App\Models\Order;

class UpdateOrderRequest extends OrderBaseRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('client_phone') && $this->has('phone')) {
            $this->merge(['client_phone' => $this->input('phone')]);
        }

        if (! $this->has('external_id') && $this->has('external_order_id')) {
            $this->merge(['external_id' => $this->input('external_order_id')]);
        }
        if ($this->has('external_id') && is_numeric($this->input('external_id'))) {
            $this->merge(['external_id' => (string) $this->input('external_id')]);
        }
    }

    public function authorize(): bool
    {
        $user = auth()->user();

        if (! $user->isOwner()) {
            $data = $this->all();

            if (array_key_exists('manager_id', $data)
                || array_key_exists('status', $data)
                || array_key_exists('site_id', $data)
            ) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'site_id' => 'sometimes|required|integer|exists:sites,id',
            'manager_id' => 'nullable|integer|exists:users,id',
            'total_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99', 'not_regex:/^0\d+/'],
            'purchase_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99', 'not_regex:/^0\d+/'],
            'status' => 'sometimes|required|string|in:created,accepted,assigned,at_warehouse,cancelled,completed',
        ], $this->commonRules());
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');
            $siteId = $this->input('site_id') ?? ($order instanceof Order ? $order->site_id : null);
            $this->validateItemsSiteConsistency($validator, $siteId ? (int) $siteId : null);
        });
    }
}
