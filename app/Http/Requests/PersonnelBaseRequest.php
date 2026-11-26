<?php

namespace App\Http\Requests;

use App\Services\RoleService;

abstract class PersonnelBaseRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->tmaUser();

        return $user !== null && app(RoleService::class)->canManageUsers($user);
    }
}
