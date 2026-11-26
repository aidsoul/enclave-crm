<?php

namespace App\Http\Requests;

class AcceptInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'initData' => 'required|string',
            'invite_token' => 'required|string|max:64',
        ];
    }
}
