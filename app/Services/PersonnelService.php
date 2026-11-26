<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use SergiX44\Nutgram\Nutgram;

class PersonnelService
{
    public function __construct(
        protected PanicService $panicService
    ) {}

    public function createPersonnel(array $data): array
    {
        $telegramUsername = null;
        try {
            $bot = app(Nutgram::class);
            $chat = $bot->getChat((string) $data['telegram_user_id']);
            $telegramUsername = $chat?->username;
        } catch (\Throwable) {
        }

        try {
            $user = User::create([
                'system_name' => $data['system_name'],
                'role' => $data['role'],
                'telegram_user_id' => (string) $data['telegram_user_id'],
                'telegram_username' => $telegramUsername,
                'is_active' => true,
            ]);

            return ['user' => $user];
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'telegram_user_id')) {
                return ['error' => 'User with this Telegram ID already exists', 'status' => 400];
            }
            throw $e;
        }
    }

    public function updatePersonnel(User $user, array $data, User $actor): array
    {
        if (array_key_exists('system_name', $data)) {
            if ($user->role === UserRole::OWNER && $user->id !== $actor->id) {
                return ['error' => 'Нельзя изменить имя другого владельца', 'status' => 422];
            }

            $newName = trim($data['system_name']);
            if ($newName === '') {
                return ['error' => 'Имя не может быть пустым', 'status' => 422];
            }

            $oldName = $user->system_name;
            if ($oldName !== $newName) {
                $user->skipAuditLog = true;
                $user->update(['system_name' => $newName]);

                ActionLog::create([
                    'actor_id' => $actor->id,
                    'target_user_id' => $user->id,
                    'order_id' => null,
                    'action_type' => ActionType::UPDATE,
                    'field_name' => 'system_name',
                    'old_value' => $oldName,
                    'new_value' => $newName,
                    'created_at' => now(),
                ]);
            }
        }

        if (array_key_exists('role', $data)) {
            if ($user->role === UserRole::OWNER) {
                return ['error' => 'Нельзя изменить роль владельца', 'status' => 422];
            }

            $newRole = $data['role'];
            if ($newRole === 'owner') {
                return ['error' => 'Нельзя назначить роль владельца', 'status' => 422];
            }

            $oldRole = $user->role->value;
            if ($oldRole !== $newRole) {
                $user->skipAuditLog = true;
                $user->update(['role' => $newRole]);

                ActionLog::create([
                    'actor_id' => $actor->id,
                    'target_user_id' => $user->id,
                    'order_id' => null,
                    'action_type' => ActionType::UPDATE,
                    'field_name' => 'role',
                    'old_value' => $oldRole,
                    'new_value' => $newRole,
                    'created_at' => now(),
                ]);
            }
        }

        if (array_key_exists('is_active', $data)) {
            $oldActive = $user->is_active;
            $newActive = (bool) $data['is_active'];

            if ($oldActive !== $newActive) {
                if (! $newActive) {
                    $this->panicService->triggerPanic($user->id, $actor->id);
                } else {
                    $user->skipAuditLog = true;
                    $user->update(['is_active' => true]);

                    ActionLog::create([
                        'actor_id' => $actor->id,
                        'target_user_id' => $user->id,
                        'order_id' => null,
                        'action_type' => ActionType::UPDATE,
                        'field_name' => 'is_active',
                        'old_value' => 'false',
                        'new_value' => 'true',
                        'created_at' => now(),
                    ]);
                }
            }
        }

        return ['user' => $user->fresh()];
    }

    public function deletePersonnel(User $user, User $actor): ?array
    {
        if ($user->id === $actor->id) {
            return ['error' => 'Cannot delete yourself', 'status' => 422];
        }

        if ($user->role === UserRole::OWNER) {
            return ['error' => 'Нельзя удалить владельца', 'status' => 422];
        }

        $user->delete();

        return null;
    }
}
