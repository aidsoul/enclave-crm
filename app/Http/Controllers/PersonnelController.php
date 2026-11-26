<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SearchPersonnelRequest;
use App\Http\Requests\StorePersonnelRequest;
use App\Http\Requests\UpdatePersonnelRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PersonnelService;
use App\Support\StrHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PersonnelController extends Controller
{
    public function __construct(
        protected PersonnelService $personnelService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $page = (int) $request->query('page', 1);

        $query = User::orderByDesc('created_at');

        if ($request->filled('role') && $request->query('role') !== 'all') {
            $query->where('role', $request->query('role'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            if ($request->query('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->query('status') === 'blocked') {
                $query->where('is_active', false);
            }
        }

        if ($search = $request->query('search')) {
            $escaped = StrHelper::escapeLike($search);
            $query->where(function ($q) use ($escaped) {
                $q->where('system_name', 'ilike', "%{$escaped}%")
                    ->orWhere('telegram_user_id', 'ilike', "%{$escaped}%")
                    ->orWhere('telegram_username', 'ilike', "%{$escaped}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = UserResource::collection($paginator->getCollection());

        return response()->json([
            'personnel' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function store(StorePersonnelRequest $request): JsonResponse
    {
        $result = $this->personnelService->createPersonnel($request->validated());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return response()->json(['user' => new UserResource($result['user'])], 201);
    }

    public function update(UpdatePersonnelRequest $request, int $id): JsonResponse
    {
        $targetUser = User::findOrFail($id);
        $result = $this->personnelService->updatePersonnel($targetUser, $request->validated(), auth()->user());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], $result['status'] ?? 422);
        }

        return response()->json(['user' => new UserResource($result['user'])]);
    }

    public function couriers(Request $request): JsonResponse
    {
        return $this->getPersonnelByRole(UserRole::COURIER, $request);
    }

    public function managers(Request $request): JsonResponse
    {
        return $this->getPersonnelByRole(UserRole::MANAGER, $request);
    }

    public function search(SearchPersonnelRequest $request): JsonResponse
    {
        $role = $request->query('role');
        $user = auth()->user();

        if ($role === 'manager' && (! $user || ! $user->isOwner())) {
            abort(403);
        }

        $roleEnum = $role === 'courier' ? UserRole::COURIER : UserRole::MANAGER;
        $limit = min(max((int) $request->query('limit', 10), 1), 100);

        return $this->getPersonnelByRole($roleEnum, $request, $limit);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $targetUser = User::findOrFail($id);
        $error = $this->personnelService->deletePersonnel($targetUser, auth()->user());

        if ($error) {
            return response()->json(['error' => $error['error']], $error['status'] ?? 422);
        }

        return response()->json(['status' => 'deleted']);
    }

    private function getPersonnelByRole(UserRole $role, Request $request, ?int $limit = null): JsonResponse
    {
        $search = $request->query('search');
        $key = $role === UserRole::COURIER ? 'couriers' : 'managers';

        if (! $search && $limit === null) {
            $cached = Cache::remember("personnel:{$role->value}:reference", 3600, function () use ($role) {
                return User::byRole($role)->active()->get(['id', 'system_name'])->map(fn ($u) => [
                    'id' => $u->id,
                    'system_name' => $u->system_name,
                ])->values()->all();
            });

            return response()->json([$key => $cached]);
        }

        $query = User::byRole($role)->active();

        if ($search) {
            $escaped = StrHelper::escapeLike($search);
            $query->where(function ($q) use ($escaped) {
                $q->where('system_name', 'ilike', "%{$escaped}%")
                    ->orWhere('telegram_user_id', 'ilike', "%{$escaped}%");
            });
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $users = $query->get(['id', 'system_name']);

        return response()->json([
            $key => $users->map(fn ($u) => [
                'id' => $u->id,
                'system_name' => $u->system_name,
            ])->values()->all(),
        ]);
    }
}
