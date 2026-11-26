<?php

namespace App\Http\Requests;

class StoreSiteRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:sites,domain',
            'default_purchase_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название сайта.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'domain.required' => 'Укажите домен сайта.',
            'domain.unique' => 'Сайт с таким доменом уже существует.',
            'domain.max' => 'Домен не должен превышать 255 символов.',
        ];
    }
}
