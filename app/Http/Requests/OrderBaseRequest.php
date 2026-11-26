<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;

abstract class OrderBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function commonRules(): array
    {
        return [
            'external_id' => 'nullable|string|max:255',
            'external_order_id' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'client_comment' => 'nullable|string|max:2000',
            'client_name' => 'nullable|string|max:255',
            'client_phone' => 'nullable|string|max:20|regex:/^\+?[0-9\s\-\(\)]{7,20}$/u',
            'client_email' => 'nullable|email|max:255',
            'client_address' => 'nullable|string|max:1000',
            'order_items' => 'nullable|array',
            'order_items.*.product_id' => 'nullable|integer|exists:products,id',
            'order_items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'order_items.*.variant_title' => 'nullable|string|max:255',
            'order_items.*.name' => 'required|string|max:255',
            'order_items.*.sku' => 'nullable|string|max:100',
            'order_items.*.qty' => ['required', 'integer', 'min:1', 'max:10000', 'not_regex:/^0\d+/'],
            'order_items.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'not_regex:/^0\d+/'],
            'order_items.*.purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'not_regex:/^0\d+/'],
            'delivery_type' => 'nullable|string|in:courier,pickup',
            'delivery_city' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'delivery_courier_id' => 'nullable|integer|exists:users,id',
            'delivery_courier_comment' => 'nullable|string|max:1000',
            'delivery_pickup_point' => 'nullable|string|max:500',
            'delivery_cost' => 'nullable|numeric|min:0|max:99999999.99',
            'delivery_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'not_regex:/^0\d+/'],
            'delivery_date' => 'nullable|date',
            'delivery_slot' => 'nullable|string|max:50',
            'payment_method' => 'nullable|string|in:cash,terminal,erip,credit,installment',
            'payment_status' => 'nullable|string|in:pending,paid,failed',
            'payment_transaction_id' => 'nullable|string|max:255',
            'payment_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'discounts' => 'nullable|array',
            'discounts.*.type' => 'required|string|in:percentage,fixed,coupon',
            'discounts.*.value' => 'required|numeric|min:0|max:99999999.99',
            'discounts.*.code' => 'nullable|string|max:100',
            'is_valuable' => 'nullable|boolean',
            'extra_data' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.required' => 'Выберите сайт.',
            'site_id.exists' => 'Выбранный сайт не существует.',
            'manager_id.exists' => 'Выбранный менеджер не существует.',
            'customer_id.exists' => 'Выбранный покупатель не существует.',
            'client_name.required' => 'Укажите имя клиента.',
            'client_name.max' => 'Имя клиента не должно превышать 255 символов.',
            'client_phone.required' => 'Укажите телефон клиента.',
            'client_phone.max' => 'Телефон не должен превышать 20 символов.',
            'client_phone.regex' => 'Некорректный формат телефона. Пример: +375 29 123-45-67.',
            'client_email.email' => 'Некорректный формат email.',
            'client_email.max' => 'Email не должен превышать 255 символов.',
            'client_address.max' => 'Адрес не должен превышать 1000 символов.',
            'order_items.*.name.required' => 'Укажите название товара.',
            'order_items.*.name.max' => 'Название товара не должно превышать 255 символов.',
            'order_items.*.qty.required' => 'Укажите количество товара.',
            'order_items.*.qty.integer' => 'Количество должно быть целым числом.',
            'order_items.*.qty.min' => 'Количество должно быть не меньше 1.',
            'order_items.*.qty.max' => 'Количество не должно превышать 10000.',
            'order_items.*.qty.not_regex' => 'Количество не должно начинаться с нуля.',
            'order_items.*.price.numeric' => 'Цена должна быть числом.',
            'order_items.*.price.min' => 'Цена не может быть отрицательной.',
            'order_items.*.price.max' => 'Цена слишком большая.',
            'order_items.*.price.not_regex' => 'Цена не должна начинаться с нуля.',
            'order_items.*.purchase_price.numeric' => 'Закупочная цена должна быть числом.',
            'order_items.*.purchase_price.min' => 'Закупочная цена не может быть отрицательной.',
            'order_items.*.purchase_price.max' => 'Закупочная цена слишком большая.',
            'order_items.*.purchase_price.not_regex' => 'Закупочная цена не должна начинаться с нуля.',
            'total_price.required' => 'Укажите цену для клиента.',
            'total_price.numeric' => 'Цена для клиента должна быть числом.',
            'total_price.min' => 'Цена для клиента не может быть отрицательной.',
            'total_price.max' => 'Цена слишком большая.',
            'total_price.not_regex' => 'Цена для клиента не должна начинаться с нуля.',
            'purchase_price.numeric' => 'Закупочная цена должна быть числом.',
            'purchase_price.min' => 'Закупочная цена не может быть отрицательной.',
            'purchase_price.max' => 'Закупочная цена слишком большая.',
            'purchase_price.not_regex' => 'Закупочная цена не должна начинаться с нуля.',
            'delivery_price.numeric' => 'Стоимость доставки должна быть числом.',
            'delivery_price.min' => 'Стоимость доставки не может быть отрицательной.',
            'delivery_price.max' => 'Стоимость доставки слишком большая.',
            'delivery_price.not_regex' => 'Стоимость доставки не должна начинаться с нуля.',
            'delivery_courier_id.exists' => 'Выбранный курьер не существует.',
            'discounts.*.type.required' => 'Укажите тип скидки.',
            'discounts.*.value.required' => 'Укажите значение скидки.',
        ];
    }

    public function attributes(): array
    {
        return [
            'external_id' => 'внешний ID заказа',
            'external_order_id' => 'внешний ID заказа',
            'site_id' => 'сайт',
            'manager_id' => 'менеджер',
            'client_name' => 'имя клиента',
            'client_phone' => 'телефон',
            'client_email' => 'email',
            'client_address' => 'адрес',
            'order_items' => 'позиции заказа',
            'delivery_price' => 'стоимость доставки',
            'delivery_cost' => 'себестоимость доставки',
            'total_price' => 'цена для клиента',
            'purchase_price' => 'закупочная цена',
            'is_valuable' => 'ценное отправление',
        ];
    }

    protected function validateItemsSiteConsistency($validator, ?int $siteId): void
    {
        if (! $siteId) {
            return;
        }

        $items = $this->input('order_items', []);
        if (! is_array($items) || empty($items)) {
            return;
        }

        $productIds = [];
        $variantIds = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (! empty($item['product_id']) && is_numeric($item['product_id'])) {
                $productIds[] = (int) $item['product_id'];
            }
            if (! empty($item['product_variant_id']) && is_numeric($item['product_variant_id'])) {
                $variantIds[] = (int) $item['product_variant_id'];
            }
        }

        $products = ! empty($productIds)
            ? Product::whereIn('id', array_unique($productIds))->get()->keyBy('id')
            : collect();

        $variants = ! empty($variantIds)
            ? ProductVariant::with('product')->whereIn('id', array_unique($variantIds))->get()->keyBy('id')
            : collect();

        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! empty($item['product_id']) && isset($products[$item['product_id']])) {
                $product = $products[$item['product_id']];
                if ($product->site_id && (int) $product->site_id !== $siteId) {
                    $validator->errors()->add("order_items.{$idx}.product_id", 'Товар не принадлежит выбранному сайту.');
                }
            }

            if (! empty($item['product_variant_id']) && isset($variants[$item['product_variant_id']])) {
                $variant = $variants[$item['product_variant_id']];
                if ($variant->product?->site_id && (int) $variant->product->site_id !== $siteId) {
                    $validator->errors()->add("order_items.{$idx}.product_variant_id", 'Вариант товара не принадлежит выбранному сайту.');
                }
            }
        }
    }
}
