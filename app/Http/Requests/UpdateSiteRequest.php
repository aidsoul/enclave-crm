<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->route('site') instanceof Site
            ? $this->route('site')->id
            : $this->route('site');

        return [
            'name' => 'sometimes|required|string|max:255',
            'domain' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('sites', 'domain')->ignore($siteId),
            ],
            'default_purchase_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
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
