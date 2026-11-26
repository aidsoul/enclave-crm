<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Services\RoleService;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->tmaUser();

        return $user !== null && app(RoleService::class)->canManageUsers($user);
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)],
            'max_activations' => ['required', 'integer', 'min:1', 'max:100'],
            'expires_at' => ['required', 'date', 'after:now'],
            'system_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
