<?php

namespace App\Http\Controllers;

use App\Enums\SettingKey;
use App\Http\Requests\UpdateRetentionRequest;
use App\Services\SettingAccessService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingService $settingService,
        protected SettingAccessService $settingAccessService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $settings = [];

        foreach (SettingKey::cases() as $key) {
            if ($this->settingAccessService->canView($user, $key)) {
                $resolved = $this->settingService->resolve($key, $user);
                $settings[$key->value] = [
                    'key' => $key->value,
                    'value' => $resolved['value'],
                    'source' => $resolved['source'],
                ];
            }
        }

        return response()->json(['settings' => $settings]);
    }

    public function retention(Request $request): JsonResponse
    {
        $retention = $this->settingService->get(SettingKey::RETENTION_DAYS, auth()->user());

        return response()->json(['retention_days' => $retention]);
    }

    public function updateRetention(UpdateRetentionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->settingService->set(SettingKey::RETENTION_DAYS, (int) $validated['retention_days'], 'global');

        return response()->json([
            'success' => true,
            'retention_days' => (int) $validated['retention_days'],
        ]);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        $settingKey = SettingKey::tryFrom($key);
        if (! $settingKey) {
            return response()->json(['error' => 'Unknown setting'], 404);
        }

        $resolved = $this->settingService->resolve($settingKey, auth()->user());

        return response()->json([
            'key' => $settingKey->value,
            'value' => $resolved['value'],
            'source' => $resolved['source'],
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $settingKey = SettingKey::tryFrom($key);
        if (! $settingKey) {
            return response()->json(['error' => 'Unknown setting'], 404);
        }

        $scope = $request->input('scope', 'user');
        $role = $request->input('role');
        $targetUserId = $request->input('user_id');

        $scopeTarget = match ($scope) {
            'global' => null,
            'role' => $role,
            'user' => $targetUserId ?? (string) auth()->user()->id,
            default => null,
        };

        $rawValue = $request->input('value');
        if ($error = $this->settingService->validateSettingValue($settingKey, $rawValue)) {
            return response()->json(['error' => $error], 422);
        }

        $this->settingService->set($settingKey, $request->input('value'), $scope, $scopeTarget);
        $resolved = $this->settingService->resolve($settingKey, auth()->user());

        return response()->json([
            'success' => true,
            'key' => $settingKey->value,
            'value' => $resolved['value'],
            'source' => $resolved['source'],
        ]);
    }
}
