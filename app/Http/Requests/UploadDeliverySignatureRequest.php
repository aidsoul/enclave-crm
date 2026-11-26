<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDeliverySignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature' => [
                'required',
                'file',
                'image',
                'mimes:png,webp',
                'dimensions:max_width=4096,max_height=4096',
                'max:1024',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $file = $this->file('signature');
            if ($file && $file->isValid()) {
                $realMime = $file->getMimeType();
                if (! in_array($realMime, ['image/png', 'image/webp'], true)) {
                    $v->errors()->add('signature', 'Файл подписи должен быть изображением в формате PNG или WEBP');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'signature.required' => 'Файл подписи обязателен для загрузки',
            'signature.file' => 'Подпись должна быть корректным файлом',
            'signature.image' => 'Файл подписи должен быть изображением',
            'signature.mimes' => 'Поддерживаются только форматы PNG и WEBP',
            'signature.max' => 'Максимальный размер файла подписи — 1 МБ',
        ];
    }
}
