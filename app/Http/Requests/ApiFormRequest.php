<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json(['error' => 'Forbidden'], 403));
    }

    protected function tmaUser(): ?User
    {
        $user = $this->attributes->get('user');

        return $user instanceof User ? $user : null;
    }
}
