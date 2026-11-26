<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StoreIncomingOrderRequest extends ApiFormRequest
{
    protected Site $site;

    public function authorize(): bool
    {
        $token = $this->header('X-API-Token');

        if (! $token) {
            Log::channel('webhooks')->warning('Missing API token', ['ip' => $this->ip()]);
            throw new HttpResponseException(response()->json(['error' => 'Unauthorized'], 401));
        }

        $site = Site::findByTokenCached($token);

        if (! $site) {
            Log::channel('webhooks')->warning('Invalid API token', ['ip' => $this->ip()]);
            throw new HttpResponseException(response()->json(['error' => 'Forbidden'], 403));
        }

        $this->site = $site;

        return true;
    }

    public function site(): Site
    {
        return $this->site;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('client_phone') && $this->has('phone')) {
            $this->merge(['client_phone' => $this->input('phone')]);
        } elseif (! $this->has('client_phone') && $this->has('client_data.phone')) {
            $this->merge(['client_phone' => $this->input('client_data.phone')]);
        }

        if (! $this->has('external_id')) {
            if ($this->has('external_order_id')) {
                $this->merge(['external_id' => $this->input('external_order_id')]);
            } elseif ($this->has('extra_data.external_order_id')) {
                $this->merge(['external_id' => $this->input('extra_data.external_order_id')]);
            } elseif ($this->has('extra_data.external_id')) {
                $this->merge(['external_id' => $this->input('extra_data.external_id')]);
            }
        }
        if ($this->has('external_id') && is_numeric($this->input('external_id'))) {
            $this->merge(['external_id' => (string) $this->input('external_id')]);
        }

        if (! $this->has('client_comment') && $this->has('comment')) {
            $this->merge(['client_comment' => $this->input('comment')]);
        }
        if (! $this->has('delivery_city') && $this->has('city')) {
            $this->merge(['delivery_city' => $this->input('city')]);
        }
        if (! $this->has('delivery_price')) {
            if ($this->has('delivery_fee')) {
                $this->merge(['delivery_price' => $this->input('delivery_fee')]);
            } elseif ($this->has('shipping_cost')) {
                $this->merge(['delivery_price' => $this->input('shipping_cost')]);
            }
        }

        if ($this->has('order_items') && is_array($this->input('order_items'))) {
            $items = array_map(function ($item) {
                if (is_array($item) && empty($item['name']) && (! empty($item['sku']) || ! empty($item['external_variant_id']) || ! empty($item['variant_id']) || ! empty($item['product_variant_id']) || ! empty($item['external_product_id']) || ! empty($item['product_id']))) {
                    $item['name'] = $item['variant_title'] ?? ($item['sku'] ?? 'Товар');
                }

                return $item;
            }, $this->input('order_items'));
            $this->merge(['order_items' => $items]);
        }

        if ($this->has('order_content') && is_array($this->input('order_content'))) {
            $items = array_map(function ($item) {
                if (is_array($item) && empty($item['name']) && (! empty($item['sku']) || ! empty($item['external_variant_id']) || ! empty($item['variant_id']) || ! empty($item['product_variant_id']) || ! empty($item['external_product_id']) || ! empty($item['product_id']))) {
                    $item['name'] = $item['variant_title'] ?? ($item['sku'] ?? 'Товар');
                }

                return $item;
            }, $this->input('order_content'));
            $this->merge(['order_content' => $items]);
        }

        if (! $this->has('fingerprint') && $this->has('visitor_id')) {
            $this->merge(['fingerprint' => $this->input('visitor_id')]);
        }
    }

    public function rules(): array
    {
        return [
            'external_id' => 'nullable|string|max:255',
            'external_order_id' => 'nullable|string|max:255',
            'client_comment' => 'nullable|string|max:2000',
            'comment' => 'nullable|string|max:2000',
            'client_name' => 'nullable|string|max:255',
            'client_phone' => 'nullable|string|max:20|regex:/^\+?[0-9\s\-\(\)]{7,20}$/u',
            'client_email' => 'nullable|email|max:255',
            'client_address' => 'nullable|string|max:1000',
            'client_data' => 'nullable|array',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'client_ip' => 'nullable|string|max:45',
            'fingerprint' => 'nullable|string|max:128',
            'visitor_id' => 'nullable|string|max:128',
            'user_agent' => 'nullable|string|max:500',
            'accept_language' => 'nullable|string|max:100',
            '_honey' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'hp_comment' => 'nullable|string|max:255',
            'order_items' => 'required_without:order_content|nullable|array|min:1',
            'order_content' => 'required_without:order_items|nullable|array|min:1',
            'order_items.*.name' => 'required|string|max:255',
            'order_items.*.variant_title' => 'nullable|string|max:255',
            'order_items.*.variant_id' => 'nullable|integer',
            'order_items.*.product_variant_id' => 'nullable|integer',
            'order_items.*.external_variant_id' => 'nullable|string|max:255',
            'order_items.*.product_id' => 'nullable|integer',
            'order_items.*.external_product_id' => 'nullable|string|max:255',
            'order_items.*.sku' => 'nullable|string|max:100',
            'order_items.*.qty' => 'required|integer|min:1|max:10000',
            'order_items.*.price' => 'nullable|numeric|min:0',
            'order_items.*.purchase_price' => 'nullable|numeric|min:0',
            'order_content.*.name' => 'required|string|max:255',
            'order_content.*.variant_title' => 'nullable|string|max:255',
            'order_content.*.variant_id' => 'nullable|integer',
            'order_content.*.product_variant_id' => 'nullable|integer',
            'order_content.*.external_variant_id' => 'nullable|string|max:255',
            'order_content.*.product_id' => 'nullable|integer',
            'order_content.*.external_product_id' => 'nullable|string|max:255',
            'order_content.*.sku' => 'nullable|string|max:100',
            'order_content.*.qty' => 'required|integer|min:1|max:10000',
            'order_content.*.price' => 'nullable|numeric|min:0',
            'order_content.*.purchase_price' => 'nullable|numeric|min:0',
            'delivery_type' => 'nullable|string|in:courier,pickup',
            'delivery_city' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'delivery_courier_id' => 'nullable|integer|exists:users,id',
            'delivery_courier_comment' => 'nullable|string|max:1000',
            'delivery_pickup_point' => 'nullable|string|max:500',
            'delivery_address' => 'nullable|string|max:1000',
            'delivery_cost' => 'nullable|numeric|min:0|max:99999999.99',
            'delivery_price' => 'nullable|numeric|min:0|max:99999999.99',
            'delivery_date' => 'nullable|date',
            'delivery_slot' => 'nullable|string|max:50',
            'payment_method' => 'nullable|string|in:cash,terminal,erip,credit,installment',
            'payment_status' => 'nullable|string|in:pending,paid,failed',
            'payment_transaction_id' => 'nullable|string|max:255',
            'payment_amount' => 'nullable|numeric|min:0',
            'discounts' => 'nullable|array',
            'is_valuable' => 'nullable|boolean',
            'discounts.*.type' => 'required|string|in:percentage,fixed,coupon',
            'discounts.*.value' => 'required|numeric|min:0',
            'discounts.*.code' => 'nullable|string|max:100',
            'extra_data' => 'nullable|array',
            'total_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'client_name.required' => 'Укажите имя клиента.',
            'client_phone.required' => 'Укажите телефон клиента.',
            'client_phone.max' => 'Телефон не должен превышать 20 символов.',
            'client_phone.regex' => 'Некорректный формат телефона. Пример: +375 29 123-45-67.',
            'order_items.required' => 'Укажите состав заказа.',
            'order_items.*.name.required' => 'Укажите название товара.',
            'order_items.*.qty.required' => 'Укажите количество товара.',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_phone' => 'телефон',
            'external_id' => 'внешний ID заказа',
            'external_order_id' => 'внешний ID заказа',
        ];
    }
}
