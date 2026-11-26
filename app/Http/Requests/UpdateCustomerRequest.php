<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && $this->input('phone') !== null) {
            $phone = (string) $this->input('phone');
            $normalized = PhoneNumber::normalize($phone);
            if ($normalized) {
                $this->merge(['phone' => $normalized]);
            }
        }
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $customerId = $customer instanceof Customer ? $customer->id : (int) $customer;
        $siteId = $customer instanceof Customer ? $customer->site_id : Customer::where('id', $customerId)->value('site_id');

        $phoneRule = [
            'nullable',
            'string',
            'max:20',
            'regex:/^\+?[0-9\s\-\(\)]{7,20}$/u',
        ];

        if ($siteId) {
            $phoneRule[] = Rule::unique('customers', 'phone')
                ->where('site_id', $siteId)
                ->ignore($customerId);
        }

        return [
            'name' => 'nullable|string|max:255',
            'phone' => $phoneRule,
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'is_blacklisted' => 'nullable|boolean',
            'blacklist_reason' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Имя не должно превышать 255 символов.',
            'phone.regex' => 'Некорректный формат номера телефона.',
            'phone.unique' => 'Клиент с таким номером телефона уже зарегистрирован на данном сайте.',
            'email.email' => 'Некорректный адрес электронной почты.',
            'address.max' => 'Адрес не должен превышать 1000 символов.',
        ];
    }
}
