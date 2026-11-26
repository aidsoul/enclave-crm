<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Enums\OrderStatus;
use App\Events\PanicTriggered;
use App\Models\ActionLog;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PanicService
{
    public function triggerPanic(int $userId, ?int $actorId = null): void
    {
        $user = User::findOrFail($userId);

        if (! $user->is_active) {
            return;
        }

        $user->skipAuditLog = true;
        $user->update(['is_active' => false]);

        $this->disconnectSession($user);

        $this->sendReverbEvent($user);

        $this->purgeCourierPendingProofs($user);

        $this->logAction($user, $actorId);

        Log::channel('single')->warning('Panic triggered', [
            'user_id' => $userId,
            'actor_id' => $actorId,
            'role' => $user->role->value,
        ]);
    }

    protected function disconnectSession(User $user): void
    {
        $telegramId = $user->telegram_user_id;

        Cache::forget("user_cache:{$user->id}");

        if ($telegramId) {
            Cache::forget("tg_user:{$telegramId}");
            Redis::del("session:{$telegramId}");
        }

        $tokens = Redis::smembers("user_sessions:{$user->id}");
        if (! empty($tokens)) {
            foreach ($tokens as $token) {
                Redis::del("session:{$token}");
                Redis::del("session_data:{$token}");
            }
            Redis::del("user_sessions:{$user->id}");
        }
    }

    protected function sendReverbEvent(User $user): void
    {
        if ($user->telegram_user_id) {
            event(new PanicTriggered((string) $user->telegram_user_id));
        }
    }

    protected function logAction(User $user, ?int $actorId): void
    {
        $actor = $actorId ? User::find($actorId) : null;

        ActionLog::create([
            'actor_id' => $actor?->id ?? $user->id,
            'target_user_id' => $user->id,
            'order_id' => null,
            'action_type' => ActionType::PANIC,
            'field_name' => 'is_active',
            'old_value' => 'true',
            'new_value' => 'false',
            'created_at' => now(),
        ]);
    }

    protected function purgeCourierPendingProofs(User $user): void
    {
        $deliveries = Delivery::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNotNull('photo_proof_path')
                    ->orWhereNotNull('photo_proof_paths')
                    ->orWhereNotNull('signature_path');
            })
            ->whereHas('order', function ($query) {
                $query->whereIn('status', [OrderStatus::ASSIGNED, OrderStatus::AT_WAREHOUSE]);
            })
            ->get();

        foreach ($deliveries as $delivery) {
            $delivery->deletePhotoProofFile();
            $delivery->deleteSignatureFile();
        }
    }

    public function restoreUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['is_active' => true]);
    }
}
