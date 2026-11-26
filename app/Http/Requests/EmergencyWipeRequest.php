<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class EmergencyWipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::OWNER;
    }

    public function rules(): array
    {
        return [
            'wipe_token' => ['required', 'string'],
            'confirm_code' => ['required', 'string'],
            'delete_backups' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if (! $user) {
                return;
            }

            $wipeToken = $this->input('wipe_token');
            $cachedUserId = Cache::get('wipe_token:'.$wipeToken);

            if (! $cachedUserId || (int) $cachedUserId !== (int) $user->id) {
                $validator->errors()->add('wipe_token', 'Недействительный или просроченный токен подтверждения');

                return;
            }

            $confirmCode = (string) $this->input('confirm_code');
            $pinValid = $user->wipe_confirm_code_hash && Hash::check($confirmCode, $user->wipe_confirm_code_hash);
            $nameValid = trim($confirmCode) !== '' && hash_equals(trim($user->system_name), trim($confirmCode));

            if (! $pinValid && ! $nameValid) {
                $validator->errors()->add('confirm_code', 'Код подтверждения не совпадает');

                return;
            }

            // Atomically pull the token to prevent race condition reuse
            $pulledUserId = Cache::pull('wipe_token:'.$wipeToken);
            if (! $pulledUserId || (int) $pulledUserId !== (int) $user->id) {
                $validator->errors()->add('wipe_token', 'Недействительный или уже использованный токен подтверждения');

                return;
            }

            $this->attributes->set('validated_wipe_user_id', $pulledUserId);
        });
    }
}
