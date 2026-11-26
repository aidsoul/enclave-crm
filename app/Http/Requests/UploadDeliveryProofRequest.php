<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDeliveryProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp,heic,heif',
                'max:10240',
                'dimensions:max_width=8192,max_height=8192',
            ],
            'photos' => [
                'nullable',
                'array',
                'max:3',
            ],
            'photos.*' => [
                'image',
                'mimes:jpeg,png,jpg,webp,heic,heif',
                'max:10240',
                'dimensions:max_width=8192,max_height=8192',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->hasFile('photo') && ! $this->hasFile('photos')) {
                $v->errors()->add('photo', 'Необходимо прикрепить хотя бы одно фото');

                return;
            }

            $allowedMimes = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/heic',
                'image/heif',
                'image/heic-sequence',
                'image/heif-sequence',
            ];

            $files = [];
            if ($this->hasFile('photo')) {
                $files[] = $this->file('photo');
            }
            if ($this->hasFile('photos')) {
                $photos = $this->file('photos');
                if (is_array($photos)) {
                    $files = array_merge($files, $photos);
                }
            }

            foreach ($files as $file) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }
                $realMime = $file->getMimeType();
                if (! in_array($realMime, $allowedMimes, true)) {
                    $v->errors()->add('photo', 'Загружаемый файл должен быть действительным изображением (JPEG, PNG, WEBP, HEIC)');
                    break;
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'photo.image' => 'Загружаемый файл должен быть изображением',
            'photo.mimes' => 'Поддерживаются только форматы JPEG, PNG, WEBP, HEIC',
            'photo.max' => 'Максимальный размер фото — 10 МБ',
            'photo.dimensions' => 'Разрешение фото не должно превышать 8192x8192 пикселей',
            'photos.max' => 'Можно загрузить не более 3 фото',
            'photos.*.image' => 'Каждый файл должен быть изображением',
            'photos.*.mimes' => 'Поддерживаются только форматы JPEG, PNG, WEBP, HEIC',
            'photos.*.max' => 'Максимальный размер каждого фото — 10 МБ',
            'photos.*.dimensions' => 'Разрешение каждого фото не должно превышать 8192x8192 пикселей',
        ];
    }
}
