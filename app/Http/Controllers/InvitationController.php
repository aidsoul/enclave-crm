<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\ActionLog;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $query = Invitation::with('creator')
            ->orderByDesc('created_at');

        if ($request->has('is_active')) {
            $query->where('is_active', (bool) $request->query('is_active'));
        }

        $paginator = $query->paginate($perPage);

        $items = InvitationResource::collection($paginator->getCollection());

        return response()->json([
            'invitations' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $invitation = Invitation::create([
            'token' => Str::random(64),
            'role' => $validated['role'],
            'system_name' => $validated['system_name'] ?? null,
            'max_activations' => $validated['max_activations'],
            'expires_at' => $validated['expires_at'],
            'created_by' => $user->id,
        ]);

        ActionLog::create([
            'actor_id' => $user->id,
            'action_type' => ActionType::CREATE,
            'field_name' => 'invitation',
            'old_value' => null,
            'new_value' => "Инвайт: {$invitation->role->value} (макс. активаций: {$invitation->max_activations})",
            'created_at' => now(),
        ]);

        return response()->json([
            'invitation' => new InvitationResource($invitation),
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $invitation = Invitation::findOrFail($id);
        $role = $invitation->role?->value ?? (string) $invitation->role;
        $invitation->delete();

        ActionLog::create([
            'actor_id' => auth()->id(),
            'action_type' => ActionType::DELETE,
            'field_name' => 'invitation',
            'old_value' => "Инвайт: {$role}",
            'new_value' => null,
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'revoked']);
    }

    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->invitationService->accept($validated['initData'], $validated['invite_token']);

        if (! empty($result['error'])) {
            return response()->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        $newUser = $result['user'];
        $sessionToken = $result['session_token'];
        $telegramId = $result['telegram_id'];

        if ($request->hasSession()) {
            $request->session()->put('tma_user_id', $sessionToken);
            $request->session()->put('tma_telegram_user_id', $telegramId);
            $request->session()->put('tma_role', $newUser->role->value);
            $request->session()->put('tma_name', $newUser->system_name);
            $request->session()->migrate(true);
        }

        return response()->json([
            'id' => $newUser->id,
            'role' => $newUser->role->value,
            'system_name' => $newUser->system_name,
            'telegram_user_id' => $telegramId,
            'session_token' => $sessionToken,
            'has_cloud_password' => $newUser->hasCloudPassword(),
        ]);
    }
}
