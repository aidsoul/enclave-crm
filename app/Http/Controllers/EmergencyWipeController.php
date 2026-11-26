<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Http\Requests\EmergencyWipeInitiateRequest;
use App\Http\Requests\EmergencyWipePinRequest;
use App\Http\Requests\EmergencyWipeRequest;
use App\Jobs\PurgeAllBackupsJob;
use App\Models\ActionLog;
use App\Services\DataRetentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmergencyWipeController extends Controller
{
    public function __construct(
        protected DataRetentionService $dataRetentionService
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->isOwner()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'has_pin' => ! empty($user->wipe_confirm_code_hash),
        ]);
    }

    public function setPin(EmergencyWipePinRequest $request): JsonResponse
    {
        $user = auth()->user();
        $user->setWipeConfirmCode($request->validated('pin'));

        ActionLog::create([
            'actor_id' => $user->id,
            'action_type' => ActionType::UPDATE,
            'field_name' => 'wipe_pin',
            'new_value' => 'PIN установлен',
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'PIN-код успешно установлен',
        ]);
    }

    public function initiate(EmergencyWipeInitiateRequest $request): JsonResponse
    {
        $user = auth()->user();
        $wipeToken = (string) Str::uuid();
        Cache::put('wipe_token:'.$wipeToken, $user->id, now()->addMinutes(10));

        ActionLog::create([
            'actor_id' => $user->id,
            'action_type' => ActionType::PANIC,
            'field_name' => 'wipe_initiate',
            'new_value' => 'Инициация экстренного уничтожения данных',
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'wipe_token' => $wipeToken,
        ]);
    }

    public function execute(EmergencyWipeRequest $request): JsonResponse
    {
        $user = auth()->user();
        $wipeToken = $request->validated('wipe_token');
        $cachedUserId = $request->attributes->get('validated_wipe_user_id')
            ?? Cache::pull('wipe_token:'.$wipeToken);

        if (! $cachedUserId || (int) $cachedUserId !== (int) $user->id) {
            return response()->json(['error' => 'Недействительный или уже использованный токен подтверждения'], 422);
        }

        $tables = DataRetentionService::wipeTableNames();
        $deleteBackups = $request->boolean('delete_backups');

        try {
            if ($deleteBackups) {
                PurgeAllBackupsJob::dispatch();
            }

            Log::channel('single')->critical('Emergency wipe execution started', [
                'user_id' => $user->id,
                'system_name' => $user->system_name,
                'ip' => $request->ip(),
                'delete_backups' => $deleteBackups,
            ]);

            $result = $this->dataRetentionService->wipeTables($tables, $user->id);

            return response()->json([
                'success' => true,
                'tables' => $tables,
                'deleted' => $result,
                'backups_purged' => $deleteBackups,
                'message' => 'Emergency wipe completed',
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('Emergency wipe execution failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Произошла ошибка при выполнении экстренного уничтожения данных',
            ], 500);
        }
    }
}
