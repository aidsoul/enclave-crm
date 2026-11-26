<?php

namespace App\Http\Requests;

use App\Services\RoleService;

class UpdateRetentionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->tmaUser();

        return $user !== null && app(RoleService::class)->canManageUsers($user);
    }

    public function rules(): array
    {
        return [
            'retention_days' => 'required|integer|in:0,1,3,7,30,60,90',
        ];
    }
}
