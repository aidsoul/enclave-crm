<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Services\RoleService;

class StoreOrderCommentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->tmaUser() ?? $this->user();
        if ($user === null || $user->isCourier()) {
            return false;
        }

        $order = $this->route('order');
        $orderId = $order instanceof Order ? $order->id : (int) $order;

        return app(RoleService::class)->canAccessOrder($user, $orderId);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Введите текст комментария.',
            'body.string' => 'Комментарий должен быть строкой.',
            'body.max' => 'Комментарий не должен превышать 2000 символов.',
        ];
    }
}
