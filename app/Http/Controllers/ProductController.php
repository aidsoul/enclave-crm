<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['site', 'variants' => function ($q) use ($request) {
            if ($request->has('is_active')) {
                $q->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
            }
        }]);

        if ($search = $request->query('search')) {
            $query->search($search);
        }

        if ($siteId = $request->query('site_id')) {
            $query->where('site_id', $siteId);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $sort = $request->query('sort', 'name');
        match ($sort) {
            'price_desc' => $query->orderByDesc('price'),
            'price_asc' => $query->orderBy('price'),
            'latest' => $query->orderByDesc('id'),
            default => $query->orderBy('name'),
        };

        $perPage = min(max((int) ($request->query('per_page') ?? $request->query('limit', 25)), 1), 100);
        $products = $query->paginate($perPage);

        return response()->json([
            'products' => ProductResource::collection($products),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['site', 'variants']);

        return response()->json([
            'product' => new ProductResource($product),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());
        $product->load('site');

        return response()->json([
            'product' => new ProductResource($product),
            'message' => 'Товар успешно создан',
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());
        $product->load('site');

        return response()->json([
            'product' => new ProductResource($product),
            'message' => 'Товар успешно обновлен',
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        return DB::transaction(function () use ($product) {
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $isUsed = $lockedProduct->orderItems()->exists()
                || $lockedProduct->variants()->whereHas('orderItems')->exists();

            if ($isUsed) {
                $lockedProduct->update(['is_active' => false]);
                foreach ($lockedProduct->variants()->lockForUpdate()->get() as $variant) {
                    $variant->update(['is_active' => false]);
                }

                return response()->json([
                    'message' => 'Товар архивирован, так как он используется в заказах',
                ]);
            }

            $lockedProduct->delete();

            return response()->json([
                'message' => 'Товар удален',
            ]);
        });
    }
}
