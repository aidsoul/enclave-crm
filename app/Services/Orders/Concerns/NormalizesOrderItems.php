<?php

namespace App\Services\Orders\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;

trait NormalizesOrderItems
{
    protected function normalizeOrderItems(array $data, ?float $defaultPercent = null, ?int $siteId = null): array
    {
        $defaultPercent = $defaultPercent ?? (float) config('orders.default_purchase_percent', 15.0);
        if (! empty($data['order_items'])) {
            $items = $data['order_items'];
        } elseif (! empty($data['order_content'])) {
            $items = $data['order_content'];
        } else {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $price = isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null;
            $purchasePrice = isset($item['purchase_price']) && is_numeric($item['purchase_price'])
                ? (float) $item['purchase_price']
                : null;
            $sku = isset($item['sku']) && is_string($item['sku']) ? trim($item['sku']) : null;
            $variantTitle = isset($item['variant_title']) && is_string($item['variant_title']) ? trim($item['variant_title']) : null;
            $productId = isset($item['product_id']) && is_numeric($item['product_id']) ? (int) $item['product_id'] : null;
            $variantId = isset($item['product_variant_id']) && is_numeric($item['product_variant_id'])
                ? (int) $item['product_variant_id']
                : (isset($item['variant_id']) && is_numeric($item['variant_id']) ? (int) $item['variant_id'] : null);

            $resolvedVariant = null;
            $resolvedProduct = null;

            if ($variantId) {
                $resolvedVariant = ProductVariant::with('product')->find($variantId);
                if ($siteId && $resolvedVariant?->product?->site_id && (int) $resolvedVariant->product->site_id !== (int) $siteId) {
                    $resolvedVariant = null;
                    $variantId = null;
                }
            } elseif (! empty($item['external_variant_id']) && $siteId) {
                $extVarId = (string) $item['external_variant_id'];
                $resolvedVariant = ProductVariant::where('external_id', $extVarId)
                    ->whereHas('product', fn ($q) => $q->where('site_id', $siteId))
                    ->with('product')
                    ->first();
            } elseif ($sku && $siteId) {
                $resolvedVariant = ProductVariant::where('sku', $sku)
                    ->whereHas('product', fn ($q) => $q->where('site_id', $siteId))
                    ->with('product')
                    ->first();
            }

            if ($resolvedVariant) {
                $variantId = $resolvedVariant->id;
                $productId = $resolvedVariant->product_id;
                $variantTitle = $variantTitle ?: $resolvedVariant->title;
                $sku = $sku ?: $resolvedVariant->sku;
                if ($price === null) {
                    $price = $resolvedVariant->effective_price;
                }
                if ($purchasePrice === null && $resolvedVariant->effective_purchase_price > 0) {
                    $purchasePrice = $resolvedVariant->effective_purchase_price;
                }
            } elseif (! $productId && ! empty($item['external_product_id']) && $siteId) {
                $extProdId = (string) $item['external_product_id'];
                $resolvedProduct = Product::where('site_id', $siteId)->where('external_id', $extProdId)->first();
                if ($resolvedProduct) {
                    $productId = $resolvedProduct->id;
                    if ($price === null) {
                        $price = (float) $resolvedProduct->price;
                    }
                    if ($purchasePrice === null && (float) $resolvedProduct->cost_price > 0) {
                        $purchasePrice = (float) $resolvedProduct->cost_price;
                    }
                }
            } elseif (! $productId && $sku && $siteId) {
                $resolvedProduct = Product::where('site_id', $siteId)->where('sku', $sku)->first();
                if ($resolvedProduct) {
                    $productId = $resolvedProduct->id;
                    if ($price === null) {
                        $price = (float) $resolvedProduct->price;
                    }
                    if ($purchasePrice === null && (float) $resolvedProduct->cost_price > 0) {
                        $purchasePrice = (float) $resolvedProduct->cost_price;
                    }
                }
            } elseif ($productId) {
                $resolvedProduct = Product::find($productId);
                if ($siteId && $resolvedProduct?->site_id && (int) $resolvedProduct->site_id !== (int) $siteId) {
                    $resolvedProduct = null;
                    $productId = null;
                }
            }

            $price = $price ?? 0.0;
            if ($purchasePrice === null) {
                $purchasePrice = round($price * ($defaultPercent / 100), 2);
            }

            $itemName = $item['name'] ?? null;
            if ((! $itemName || $itemName === 'Товар') && $resolvedVariant?->product?->name) {
                $itemName = $resolvedVariant->product->name;
            } elseif ((! $itemName || $itemName === 'Товар') && $resolvedProduct?->name) {
                $itemName = $resolvedProduct->name;
            } elseif (! $itemName) {
                $itemName = 'Без названия';
            }

            $extVariantId = isset($item['external_variant_id']) ? (string) $item['external_variant_id'] : ($resolvedVariant?->external_id ?? null);
            $extProductId = isset($item['external_product_id']) ? (string) $item['external_product_id'] : ($resolvedProduct?->external_id ?? ($resolvedVariant?->product?->external_id ?? null));

            $normalized[] = [
                'product_id' => $productId,
                'external_product_id' => $extProductId,
                'product_variant_id' => $variantId,
                'external_variant_id' => $extVariantId,
                'name' => $itemName,
                'variant_title' => $variantTitle,
                'sku' => $sku,
                'qty' => max((int) ($item['qty'] ?? 1), 1),
                'price' => $price,
                'purchase_price' => $purchasePrice,
            ];
        }

        return $normalized;
    }

    protected function calculatePrices(array $orderItems): array
    {
        $totalPrice = 0;
        $purchasePrice = 0;

        foreach ($orderItems as $item) {
            $price = (float) ($item['price'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            $itemPurchase = isset($item['purchase_price']) && is_numeric($item['purchase_price'])
                ? (float) $item['purchase_price']
                : 0;
            $totalPrice += $price * $qty;
            $purchasePrice += $itemPurchase * $qty;
        }

        return [
            'total_price' => round($totalPrice, 2),
            'purchase_price' => round($purchasePrice, 2),
        ];
    }
}
