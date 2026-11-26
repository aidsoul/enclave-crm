<?php

namespace App\Http\Requests;

class StoreProductRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'cost_price' => 'nullable|numeric|min:0|max:99999999.99',
            'site_id' => 'nullable|integer|exists:sites,id',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название товара.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'sku.unique' => 'Товар с таким артикулом уже существует.',
            'price.required' => 'Укажите розничную цену.',
            'price.min' => 'Цена не может быть отрицательной.',
        ];
    }
}
