<?php

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    protected array $runtimeCache = [];

    public function resolve(SettingKey $key, ?User $user = null): array
    {
        $userId = $user ? $user->id : 'guest';
        $runtimeKey = "{$userId}:{$key->value}";

        if (isset($this->runtimeCache[$runtimeKey])) {
            return $this->runtimeCache[$runtimeKey];
        }

        $keyValue = $key->value;

        if ($user) {
            $userVal = Cache::remember("setting:user:{$user->id}:{$keyValue}", 3600, function () use ($keyValue, $user) {
                $setting = Setting::where('key', $keyValue)
                    ->where('scope', 'user')
                    ->where('user_id', $user->id)
                    ->first();

                return $setting ? $setting->value : '__NOT_SET__';
            });

            if ($userVal !== '__NOT_SET__') {
                return $this->runtimeCache[$runtimeKey] = [
                    'value' => $this->castValue($userVal, $key),
                    'source' => 'user',
                ];
            }

            $roleVal = Cache::remember("setting:role:{$user->role->value}:{$keyValue}", 3600, function () use ($keyValue, $user) {
                $setting = Setting::where('key', $keyValue)
                    ->where('scope', 'role')
                    ->where('role', $user->role->value)
                    ->first();

                return $setting ? $setting->value : '__NOT_SET__';
            });

            if ($roleVal !== '__NOT_SET__') {
                return $this->runtimeCache[$runtimeKey] = [
                    'value' => $this->castValue($roleVal, $key),
                    'source' => 'role',
                ];
            }
        }

        $globalVal = Cache::remember("setting:global:{$keyValue}", 3600, function () use ($keyValue) {
            $setting = Setting::where('key', $keyValue)
                ->where('scope', 'global')
                ->first();

            return $setting ? $setting->value : '__NOT_SET__';
        });

        if ($globalVal !== '__NOT_SET__') {
            return $this->runtimeCache[$runtimeKey] = [
                'value' => $this->castValue($globalVal, $key),
                'source' => 'global',
            ];
        }

        return $this->runtimeCache[$runtimeKey] = [
            'value' => $key->default(),
            'source' => 'fallback',
        ];
    }

    public function preload(SettingKey $key, iterable $users): void
    {
        $userIds = [];
        $keyValue = $key->value;

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }
            $runtimeKey = "{$user->id}:{$keyValue}";
            if (! isset($this->runtimeCache[$runtimeKey])) {
                $userIds[] = $user->id;
            }
        }

        if (empty($userIds)) {
            return;
        }

        $userSettings = Setting::where('key', $keyValue)
            ->where('scope', 'user')
            ->whereIn('user_id', $userIds)
            ->pluck('value', 'user_id');

        foreach ($users as $user) {
            if (! $user instanceof User || ! in_array($user->id, $userIds, true)) {
                continue;
            }
            if ($userSettings->has($user->id)) {
                $val = $userSettings->get($user->id);
                Cache::put("setting:user:{$user->id}:{$keyValue}", $val, 3600);
                $this->runtimeCache["{$user->id}:{$keyValue}"] = [
                    'value' => $this->castValue($val, $key),
                    'source' => 'user',
                ];
            }
        }
    }

    public function get(SettingKey $key, ?User $user = null): mixed
    {
        return $this->resolve($key, $user)['value'];
    }

    public function getSource(SettingKey $key, ?User $user = null): string
    {
        return $this->resolve($key, $user)['source'];
    }

    public function clearRuntimeCache(): void
    {
        $this->runtimeCache = [];
    }

    public function getRaw(SettingKey $key, string $scope, ?string $scopeTarget = null): mixed
    {
        $cacheKey = "setting:raw:{$key->value}:{$scope}:".($scopeTarget ?? 'null');

        $val = Cache::remember($cacheKey, 3600, function () use ($key, $scope, $scopeTarget) {
            $query = Setting::where('key', $key->value)->where('scope', $scope);

            if ($scope === 'role' && $scopeTarget) {
                $query->where('role', $scopeTarget);
            } elseif ($scope === 'user' && $scopeTarget) {
                $query->where('user_id', (int) $scopeTarget);
            }

            $setting = $query->first();

            return $setting !== null ? $setting->value : '__NOT_SET__';
        });

        return $val !== '__NOT_SET__' ? $this->castValue($val, $key) : null;
    }

    public function set(SettingKey $key, mixed $value, string $scope, ?string $scopeTarget = null): void
    {
        $attributes = [
            'key' => $key->value,
            'value' => $this->stringifyValue($value),
        ];

        if ($scope === 'global') {
            $attributes['scope'] = 'global';
            $attributes['user_id'] = null;
            $attributes['role'] = null;
        } elseif ($scope === 'role') {
            $attributes['scope'] = 'role';
            $attributes['user_id'] = null;
            $attributes['role'] = $scopeTarget;
        } elseif ($scope === 'user') {
            $attributes['scope'] = 'user';
            $attributes['user_id'] = $scopeTarget !== null ? (int) $scopeTarget : null;
            $attributes['role'] = null;
        }

        Setting::updateOrCreate(
            [
                'key' => $key->value,
                'scope' => $attributes['scope'],
                'user_id' => $attributes['user_id'],
                'role' => $attributes['role'],
            ],
            ['value' => $attributes['value']]
        );

        if ($scope === 'global') {
            Cache::forget("setting:global:{$key->value}");
        } elseif ($scope === 'role' && ! empty($attributes['role'])) {
            Cache::forget("setting:role:{$attributes['role']}:{$key->value}");
        } elseif ($scope === 'user' && ! empty($attributes['user_id'])) {
            Cache::forget("setting:user:{$attributes['user_id']}:{$key->value}");
        }

        Cache::forget("setting:raw:{$key->value}:{$scope}:".($scopeTarget ?? 'null'));
        $this->clearRuntimeCache();
    }

    protected function castValue(string $value, SettingKey $key): mixed
    {
        return match ($key->type()) {
            'int' => (int) $value,
            'string' => (string) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        };
    }

    protected function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    public function validateSettingValue(SettingKey $key, mixed $rawValue): ?string
    {
        if ($key === SettingKey::RETENTION_DAYS) {
            if (! in_array((int) $rawValue, [0, 1, 3, 7, 30, 60, 90, 180, 365], true) && ! in_array((string) $rawValue, ['0', '1', '3', '7', '30', '60', '90', '180', '365'], true)) {
                return 'Недопустимое значение дней хранения';
            }
        } elseif ($key->type() === 'int') {
            if (! is_numeric($rawValue) || (int) $rawValue < 0) {
                return 'Значение должно быть положительным числом';
            }
        } elseif ($key->type() === 'bool') {
            if (! is_bool($rawValue) && ! in_array($rawValue, [0, 1, '0', '1', 'true', 'false'], true)) {
                return 'Значение должно быть логическим (true/false)';
            }
        }

        return null;
    }
}
