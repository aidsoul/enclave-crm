<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCatalogProductRequest;
use App\Http\Requests\SyncCatalogRequest;
use App\Http\Resources\ProductResource;
use App\Jobs\SyncCatalogJob;
use App\Models\Product;
use App\Models\Site;
use App\Services\Catalog\CatalogSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CatalogSyncController extends Controller
{
    public function sync(SyncCatalogRequest $request, CatalogSynchronizer $synchronizer): JsonResponse
    {
        $site = $request->site();
        $products = $request->validated('products');

        if ($request->boolean('async')) {
            $jobId = uniqid('catalog_sync_');
            SyncCatalogJob::dispatch($site->id, $products);

            Log::channel('webhooks')->info('Catalog sync queued', [
                'job_id' => $jobId,
                'site_id' => $site->id,
                'products_count' => count($products),
            ]);

            return response()->json([
                'message' => 'Каталог поставлен в очередь на синхронизацию',
                'job_id' => $jobId,
            ], 202);
        }

        $result = $synchronizer->sync($site->id, $products);

        Log::channel('webhooks')->info('Catalog sync executed synchronously', [
            'site_id' => $site->id,
            'synced_products' => $result['synced_products'],
            'synced_variants' => $result['synced_variants'],
        ]);

        return response()->json([
            'message' => 'Каталог успешно синхронизирован',
            'synced_products' => $result['synced_products'],
            'synced_variants' => $result['synced_variants'],
        ]);
    }

    public function storeOrUpdateProduct(StoreCatalogProductRequest $request, CatalogSynchronizer $synchronizer): JsonResponse
    {
        $site = $request->site();
        $product = $synchronizer->syncProduct($site->id, $request->validated());

        return response()->json([
            'message' => 'Товар успешно сохранен',
            'product' => new ProductResource($product),
        ]);
    }

    public function destroyProduct(Request $request, string $externalId): JsonResponse
    {
        $token = $request->header('X-API-Token');
        if (! $token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $site = Site::findByTokenCached($token);
        if (! $site) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return DB::transaction(function () use ($site, $externalId) {
            $lockedProduct = Product::where('site_id', $site->id)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if (! $lockedProduct) {
                return response()->json(['error' => 'Product not found'], 404);
            }

            $isUsed = $lockedProduct->orderItems()->exists()
                || $lockedProduct->variants()->whereHas('orderItems')->exists();

            if ($isUsed) {
                $lockedProduct->update(['is_active' => false]);
                foreach ($lockedProduct->variants()->lockForUpdate()->get() as $variant) {
                    $variant->update(['is_active' => false]);
                }

                return response()->json([
                    'message' => 'Товар архивирован, так как он используется в заказах',
                    'archived' => true,
                ]);
            }

            $lockedProduct->delete();

            return response()->json([
                'message' => 'Товар успешно удален',
                'deleted' => true,
            ]);
        });
    }
}
