<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Site;
use App\Support\StrHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user && $user->isOwner()) {
            $query = Site::withCount('orders');

            if ($search = $request->query('search')) {
                $escaped = StrHelper::escapeLike(trim($search));
                $query->where(function ($q) use ($escaped) {
                    $q->where('name', 'ilike', "%{$escaped}%")
                        ->orWhere('domain', 'ilike', "%{$escaped}%");
                });
            }

            if ($request->boolean('all')) {
                $sites = $query->orderBy('name')->get()->makeVisible(['api_token']);

                return response()->json(['sites' => $sites]);
            }

            $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
            $paginated = $query->orderBy('name')->paginate($perPage);
            $paginated->getCollection()->makeVisible(['api_token']);

            return response()->json([
                'sites' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        $sites = Site::getCachedActive();

        return response()->json(['sites' => $sites]);
    }

    public function show(Site $site): JsonResponse
    {
        $site->loadCount('orders');
        $site->makeVisible(['api_token']);

        return response()->json(['site' => $site]);
    }

    public function store(StoreSiteRequest $request): JsonResponse
    {
        $site = Site::create($request->validated());
        $site->makeVisible(['api_token']);

        return response()->json([
            'site' => $site,
            'message' => 'Сайт успешно создан',
        ], 201);
    }

    public function update(UpdateSiteRequest $request, Site $site): JsonResponse
    {
        $site->update($request->validated());
        $site->makeVisible(['api_token']);

        return response()->json([
            'site' => $site,
            'message' => 'Сайт успешно обновлен',
        ]);
    }

    public function rotateToken(Site $site): JsonResponse
    {
        $newToken = $site->rotateApiToken();

        return response()->json([
            'api_token' => $newToken,
            'message' => 'API-токен успешно сгенерирован',
        ]);
    }

    public function revokeToken(Site $site): JsonResponse
    {
        $site->revokeApiToken();

        return response()->json([
            'message' => 'API-токен успешно отозван',
        ]);
    }

    public function destroy(Site $site): JsonResponse
    {
        if ($site->orders()->exists()) {
            $site->update(['is_active' => false]);

            return response()->json([
                'message' => 'Сайт деактивирован, так как к нему привязаны заказы',
            ]);
        }

        $site->delete();

        return response()->json([
            'message' => 'Сайт удален',
        ]);
    }
}
