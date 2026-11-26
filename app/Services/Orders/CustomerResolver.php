<?php

namespace App\Services\Orders;

use App\Models\Customer;
use App\Support\PhoneNumber;
use Illuminate\Database\QueryException;

class CustomerResolver
{
    public function resolve(int $siteId, array $data): ?Customer
    {
        if (! empty($data['customer_id'])) {
            $customer = Customer::where('id', $data['customer_id'])
                ->where('site_id', $siteId)
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        $name = $data['client_name'] ?? ($data['client_data']['name'] ?? null);
        $phone = $data['client_phone'] ?? ($data['client_data']['phone'] ?? null);
        $email = $data['client_email'] ?? ($data['client_data']['email'] ?? null);
        $address = $data['client_address'] ?? ($data['client_data']['address'] ?? null);

        $name = isset($name) ? trim((string) $name) : null;
        $name = $name !== '' ? $name : null;

        $phone = isset($phone) ? trim((string) $phone) : null;
        $phone = $phone !== '' ? $phone : null;

        $email = isset($email) ? trim((string) $email) : null;
        $email = $email !== '' ? $email : null;

        $address = isset($address) ? trim((string) $address) : null;
        $address = $address !== '' ? $address : null;

        if (! $name && ! $phone && ! $email) {
            return null;
        }

        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone || $phone) {
            $customer = Customer::where('site_id', $siteId)
                ->where(function ($query) use ($normalizedPhone, $phone) {
                    if ($normalizedPhone) {
                        $query->where('phone', $normalizedPhone)
                            ->orWhere('phone', '+'.$normalizedPhone);
                    }
                    if ($phone && $phone !== $normalizedPhone && $phone !== ('+'.$normalizedPhone)) {
                        $query->orWhere('phone', $phone);
                    }
                })
                ->lockForUpdate()
                ->first();

            if ($customer) {
                $updateData = [];

                if ($normalizedPhone && $customer->phone !== $normalizedPhone) {
                    $updateData['phone'] = $normalizedPhone;
                }

                if ($name && (! $this->isPlaceholderName($name) || $this->isPlaceholderName($customer->name))) {
                    $updateData['name'] = $name;
                }

                if ($email) {
                    $updateData['email'] = $email;
                }

                if ($address) {
                    $updateData['address'] = $address;
                }

                if (! empty($updateData)) {
                    $customer->update($updateData);
                }

                return $customer;
            }
        } elseif ($email) {
            $customer = Customer::where('site_id', $siteId)
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($customer) {
                $updateData = [];

                if ($name && (! $this->isPlaceholderName($name) || $this->isPlaceholderName($customer->name))) {
                    $updateData['name'] = $name;
                }

                if ($address && empty($customer->address)) {
                    $updateData['address'] = $address;
                }

                if (! empty($updateData)) {
                    $customer->update($updateData);
                }

                return $customer;
            }
        }

        $phoneToSave = $normalizedPhone ?: $phone;
        $finalName = $name ?: ($phoneToSave ? "Клиент {$phoneToSave}" : 'Клиент');

        try {
            return Customer::create([
                'site_id' => $siteId,
                'name' => $finalName,
                'phone' => $phoneToSave,
                'email' => $email,
                'address' => $address,
            ]);
        } catch (QueryException $e) {
            if ($phoneToSave) {
                $existing = Customer::where('site_id', $siteId)
                    ->where('phone', $phoneToSave)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            } elseif ($email) {
                $existing = Customer::where('site_id', $siteId)
                    ->where('email', $email)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    public function normalizePhone(?string $phone): ?string
    {
        return PhoneNumber::normalize($phone);
    }

    protected function isPlaceholderName(?string $name): bool
    {
        if ($name === null) {
            return true;
        }

        $trimmed = trim($name);
        if ($trimmed === '') {
            return true;
        }

        $lower = mb_strtolower($trimmed);

        if (in_array($lower, ['клиент', 'клиент сайта', 'заказ с сайта', 'без имени', 'покупатель', 'не указано', '—', '-'], true)) {
            return true;
        }

        return (bool) preg_match('/^клиент\s*(\+?\d+)?$/u', $lower);
    }
}
