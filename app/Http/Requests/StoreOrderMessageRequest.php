<?php

namespace App\Http\Requests;

use App\Services\RoleService;

class StoreOrderMessageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->tmaUser() ?? $this->user();
        if ($user === null) {
            return false;
        }

        $order = $this->route('order');

        if (! app(RoleService::class)->canAccessOrderChat($user, $order)) {
            return false;
        }

        $channel = $this->input('channel', 'courier');
        if ($user->isCourier() && $channel !== 'courier') {
            return false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('message') && is_string($this->message)) {
            $cleaned = trim(strip_tags($this->message));
            $this->merge([
                'message' => $cleaned !== '' ? $cleaned : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'channel' => ['nullable', 'string', 'in:courier,internal'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Введите текст сообщения.',
            'message.string' => 'Сообщение должно быть строкой.',
            'message.max' => 'Сообщение не должно превышать 2000 символов.',
        ];
    }
}
