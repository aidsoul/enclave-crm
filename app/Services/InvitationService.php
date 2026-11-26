<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Events\UserRegisteredViaInvitation;
use App\Models\ActionLog;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Telegram\TelegramInitDataValidator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(
        protected TelegramInitDataValidator $telegramValidator,
        protected UserSessionService $sessionService,
        protected TelegramService $telegramService
    ) {}

    /**
     * @return array{user?: User, session_token?: string, telegram_id?: string, error?: string, status?: int}
     */
    public function accept(string $initData, string $inviteToken): array
    {
        try {
            $telegramUser = $this->telegramValidator->getTelegramUser($initData);
            $telegramId = (string) $telegramUser->id;
            $firstName = $telegramUser->first_name ?? null;
        } catch (\InvalidArgumentException) {
            return ['error' => 'Invalid initData', 'status' => 400];
        }

        $result = DB::transaction(function () use ($inviteToken, $telegramId, $telegramUser, $firstName) {
            $invitation = Invitation::where('token', $inviteToken)
                ->where('is_active', true)
                ->with('creator')
                ->lockForUpdate()
                ->first();

            if (! $invitation) {
                return ['error' => 'Invite not found', 'status' => 400];
            }

            if ($invitation->expires_at->isPast()) {
                return ['error' => 'Invite expired', 'status' => 400];
            }

            if ($invitation->used_count >= $invitation->max_activations) {
                return ['error' => 'Invite limit reached', 'status' => 400];
            }

            $existingUser = User::where('telegram_user_id', $telegramId)->lockForUpdate()->first();
            if ($existingUser) {
                if (! $existingUser->is_active) {
                    return ['error' => 'User is inactive', 'status' => 403];
                }

                return [
                    'user' => $existingUser,
                    'invitation' => $invitation,
                    'already_exists' => true,
                ];
            }

            $systemName = $invitation->system_name ?: ($firstName ?: 'User');

            try {
                $newUser = User::create([
                    'system_name' => $systemName,
                    'role' => $invitation->role,
                    'telegram_user_id' => $telegramId,
                    'telegram_username' => $telegramUser->username ?? null,
                    'is_active' => true,
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'telegram_user_id')) {
                    $existingUser = User::where('telegram_user_id', $telegramId)->first();
                    if ($existingUser && $existingUser->is_active) {
                        return [
                            'user' => $existingUser,
                            'invitation' => $invitation,
                            'already_exists' => true,
                        ];
                    }

                    return ['error' => 'User with this Telegram ID already exists', 'status' => 409];
                }
                throw $e;
            }

            $invitation->update([
                'used_count' => $invitation->used_count + 1,
                'last_used_at' => now(),
            ]);

            ActionLog::create([
                'actor_id' => $newUser->id,
                'target_user_id' => $newUser->id,
                'action_type' => ActionType::AUTH,
                'field_name' => 'invitation_accept',
                'old_value' => null,
                'new_value' => "Инвайт активирован (ID: {$invitation->id}, роль: {$invitation->role->value})",
                'created_at' => now(),
            ]);

            return [
                'user' => $newUser,
                'invitation' => $invitation,
            ];
        });

        if (! empty($result['error'])) {
            return $result;
        }

        $newUser = $result['user'];
        $invitation = $result['invitation'];

        $sessionToken = Str::random(64);
        $this->sessionService->registerSession($newUser, $sessionToken, (int) $telegramId);

        if (empty($result['already_exists']) && $invitation->creator && $invitation->creator->isOwner()) {
            event(new UserRegisteredViaInvitation($newUser, $invitation->creator));

            try {
                if (config('nutgram.token')) {
                    $this->telegramService->notifyOwnersAboutNewUser($newUser, $invitation->creator);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to send Telegram notification about new user', [
                    'new_user_id' => $newUser->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'user' => $newUser,
            'session_token' => $sessionToken,
            'telegram_id' => $telegramId,
        ];
    }
}
