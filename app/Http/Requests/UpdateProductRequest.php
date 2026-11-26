<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product') instanceof Product
            ? $this->route('product')->id
            : $this->route('product');

        return [
            'name' => 'sometimes|required|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'description' => 'nullable|string|max:2000',
            'price' => 'sometimes|required|numeric|min:0|max:99999999.99',
            'cost_price' => 'nullable|numeric|min:0|max:99999999.99',
            'site_id' => 'nullable|integer|exists:sites,id',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название товара.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'sku.unique' => 'Товар с таким артикулом уже существует.',
            'price.required' => 'Укажите розничную цену.',
        ];
    }
}
