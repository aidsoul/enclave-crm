<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StoreIncomingLeadRequest extends ApiFormRequest
{
    protected Site $site;

    public function authorize(): bool
    {
        $token = $this->header('X-API-Token');

        if (! $token) {
            Log::channel('webhooks')->warning('Missing API token on incoming lead', ['ip' => $this->ip()]);
            throw new HttpResponseException(response()->json(['error' => 'Unauthorized'], 401));
        }

        $site = Site::findByTokenCached($token);

        if (! $site) {
            Log::channel('webhooks')->warning('Invalid API token on incoming lead', ['ip' => $this->ip()]);
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
        }
        if (! $this->has('client_name') && $this->has('name')) {
            $this->merge(['client_name' => $this->input('name')]);
        }
        if (! $this->has('client_email') && $this->has('email')) {
            $this->merge(['client_email' => $this->input('email')]);
        }
        if (! $this->has('message') && $this->has('comment')) {
            $this->merge(['message' => $this->input('comment')]);
        }
        if (! $this->has('type') && $this->has('form_type')) {
            $this->merge(['type' => $this->input('form_type')]);
        }
        if (! $this->has('type')) {
            $this->merge(['type' => 'callback']);
        }
        if ($this->has('external_id') && is_numeric($this->input('external_id'))) {
            $this->merge(['external_id' => (string) $this->input('external_id')]);
        }
        if (! $this->has('fingerprint') && $this->has('visitor_id')) {
            $this->merge(['fingerprint' => $this->input('visitor_id')]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|string|in:callback,contact,question,one_click,consultation',
            'client_phone' => 'required_without:client_email|nullable|string|max:20|regex:/^\+?[0-9\s\-\(\)]{7,20}$/u',
            'client_email' => 'required_without:client_phone|nullable|email|max:255',
            'client_name' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:2000',
            'product_name' => 'nullable|string|max:255',
            'product_sku' => 'nullable|string|max:100',
            'page_url' => 'nullable|string|max:1000',
            'page_title' => 'nullable|string|max:255',
            'external_id' => 'nullable|string|max:255',
            'extra_data' => 'nullable|array',
            'client_ip' => 'nullable|string|max:45',
            'fingerprint' => 'nullable|string|max:128',
            'user_agent' => 'nullable|string|max:500',
            'visitor_id' => 'nullable|string|max:128',
            'accept_language' => 'nullable|string|max:100',
            '_honey' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'hp_comment' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'client_phone.required_without' => 'Укажите номер телефона или email.',
            'client_email.required_without' => 'Укажите email или номер телефона.',
            'client_phone.regex' => 'Некорректный формат телефона. Пример: +375 29 123-45-67.',
            'client_email.email' => 'Некорректный email адрес.',
            'message.max' => 'Сообщение не должно превышать 2000 символов.',
        ];
    }
}
