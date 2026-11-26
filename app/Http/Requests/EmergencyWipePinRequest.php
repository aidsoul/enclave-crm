<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class EmergencyWipePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::OWNER;
    }

    public function rules(): array
    {
        return [
            'current_pin' => ['nullable', 'string'],
            'pin' => ['required', 'string', 'confirmed'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if (! $user) {
                return;
            }

            $lockKey = "wipe_pin_lock:{$user->id}";
            if (Cache::has($lockKey)) {
                $validator->errors()->add('current_pin', 'Слишком много неверных попыток. Экстренное уничтожение временно заблокировано.');

                return;
            }

            if ($user->wipe_confirm_code_hash) {
                $currentPin = $this->input('current_pin');
                if (! $currentPin || ! Hash::check($currentPin, $user->wipe_confirm_code_hash)) {
                    $attemptKey = "wipe_pin_attempts:{$user->id}";
                    $attempts = (int) Cache::increment($attemptKey);
                    if ($attempts === 1) {
                        Cache::put($attemptKey, 1, now()->addMinutes(15));
                    }

                    if ($attempts >= 5) {
                        Cache::put($lockKey, true, now()->addMinutes(15));
                        Cache::forget($attemptKey);
                    }

                    $validator->errors()->add('current_pin', 'Неверный текущий PIN-код');
                } else {
                    Cache::forget("wipe_pin_attempts:{$user->id}");
                }
            }
        });
    }
}
