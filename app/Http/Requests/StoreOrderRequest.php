<?php

namespace App\Http\Requests;

class StoreOrderRequest extends OrderBaseRequest
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

    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'site_id' => 'required|integer|exists:sites,id',
            'client_phone' => 'nullable|string|max:20|regex:/^\+?[0-9\s\-\(\)]{7,20}$/u',
            'total_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'not_regex:/^0\d+/'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'not_regex:/^0\d+/'],
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $siteId = $this->input('site_id');
            $this->validateItemsSiteConsistency($validator, $siteId ? (int) $siteId : null);
        });
    }
}
