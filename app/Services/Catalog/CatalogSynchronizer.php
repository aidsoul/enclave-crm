<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class CatalogSynchronizer
{
    /**
     * @param  array<int, array<string, mixed>>  $productsData
     * @return array{synced_products: int, synced_variants: int}
     */
    public function sync(int $siteId, array $productsData): array
    {
        $syncedProducts = 0;
        $syncedVariants = 0;

        DB::transaction(function () use ($siteId, $productsData, &$syncedProducts, &$syncedVariants) {
            foreach ($productsData as $item) {
                $this->syncProduct($siteId, $item, false);
                $syncedProducts++;
                $syncedVariants += isset($item['variants']) && is_array($item['variants']) ? count($item['variants']) : 0;
            }
        });

        return [
            'synced_products' => $syncedProducts,
            'synced_variants' => $syncedVariants,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function syncProduct(int $siteId, array $item, bool $loadRelations = true): Product
    {
        return DB::transaction(function () use ($siteId, $item, $loadRelations) {
            $externalId = (string) $item['external_id'];

            $product = Product::updateOrCreate(
                [
                    'site_id' => $siteId,
                    'external_id' => $externalId,
                ],
                [
                    'name' => $item['name'],
                    'sku' => $item['sku'] ?? null,
                    'description' => $item['description'] ?? null,
                    'image_url' => $item['image_url'] ?? null,
                    'url' => $item['url'] ?? null,
                    'price' => isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : 0,
                    'cost_price' => isset($item['cost_price']) && is_numeric($item['cost_price']) ? (float) $item['cost_price'] : 0,
                    'is_active' => $item['is_active'] ?? true,
                ]
            );

            if (isset($item['variants']) && is_array($item['variants'])) {
                $newVariantExternalIds = [];
                foreach ($item['variants'] as $v) {
                    $variantExternalId = (string) $v['external_id'];
                    $newVariantExternalIds[] = $variantExternalId;

                    ProductVariant::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'external_id' => $variantExternalId,
                        ],
                        [
                            'sku' => $v['sku'] ?? null,
                            'title' => $v['title'],
                            'attributes' => $v['attributes'] ?? null,
                            'price' => isset($v['price']) && is_numeric($v['price']) ? (float) $v['price'] : null,
                            'purchase_price' => isset($v['purchase_price']) && is_numeric($v['purchase_price']) ? (float) $v['purchase_price'] : null,
                            'is_active' => $v['is_active'] ?? true,
                        ]
                    );
                }

                if (! empty($newVariantExternalIds)) {
                    $product->variants()->whereNotIn('external_id', $newVariantExternalIds)->update(['is_active' => false]);
                }
            }

            return $loadRelations ? $product->load('variants') : $product;
        });
    }
}
