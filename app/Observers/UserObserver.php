<?php

namespace App\Observers;

use App\Enums\ActionType;
use App\Models\ActionLog;
use App\Models\User;
use App\Services\UserSessionService;
use App\Services\UserSettingsInitializer;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    public function created(User $user): void
    {
        Cache::forget("user_cache:{$user->id}");
        Cache::forget('personnel:courier:reference');
        Cache::forget('personnel:manager:reference');

        app(UserSettingsInitializer::class)->initialize($user);

        $actor = request()?->attributes?->get('user') ?? auth()->user();
        ActionLog::create([
            'actor_id' => $actor instanceof User ? $actor->id : null,
            'target_user_id' => $user->id,
            'action_type' => ActionType::CREATE,
            'field_name' => 'user',
            'old_value' => null,
            'new_value' => "{$user->system_name} ({$user->role->value})",
            'created_at' => now(),
        ]);
    }

    public function updated(User $user): void
    {
        Cache::forget("user_cache:{$user->id}");
        Cache::forget('personnel:courier:reference');
        Cache::forget('personnel:manager:reference');
        if ($user->telegram_user_id) {
            Cache::forget("tg_user:{$user->telegram_user_id}");
        }

        if (! $user->is_active) {
            app(UserSessionService::class)->revokeAllOtherSessions($user, null);
        }

        if (empty($user->skipAuditLog)) {
            $actor = request()?->attributes?->get('user') ?? auth()->user();
            $actorId = $actor instanceof User ? $actor->id : null;

            foreach (['system_name', 'role', 'is_active', 'telegram_user_id', 'telegram_username'] as $field) {
                if ($user->wasChanged($field)) {
                    $old = $user->getOriginal($field);
                    $new = $user->$field;

                    ActionLog::create([
                        'actor_id' => $actorId,
                        'target_user_id' => $user->id,
                        'action_type' => $field === 'is_active' ? ActionType::STATUS_CHANGE : ActionType::UPDATE,
                        'field_name' => $field,
                        'old_value' => is_bool($old) ? ($old ? 'true' : 'false') : (string) ($old instanceof \BackedEnum ? $old->value : $old),
                        'new_value' => is_bool($new) ? ($new ? 'true' : 'false') : (string) ($new instanceof \BackedEnum ? $new->value : $new),
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }

    public function deleting(User $user): void
    {
        $actor = request()?->attributes?->get('user') ?? auth()->user();
        ActionLog::create([
            'actor_id' => $actor instanceof User ? $actor->id : null,
            'target_user_id' => $user->id,
            'action_type' => ActionType::DELETE,
            'field_name' => 'user',
            'old_value' => "{$user->system_name} ({$user->role->value})",
            'new_value' => null,
            'created_at' => now(),
        ]);
    }

    public function deleted(User $user): void
    {
        Cache::forget("user_cache:{$user->id}");
        Cache::forget('personnel:courier:reference');
        Cache::forget('personnel:manager:reference');
        if ($user->telegram_user_id) {
            Cache::forget("tg_user:{$user->telegram_user_id}");
        }
        app(UserSessionService::class)->revokeAllOtherSessions($user, null);
    }
}
