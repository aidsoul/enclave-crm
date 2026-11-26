<?php

namespace App\Observers;

use App\Enums\ActionType;
use App\Models\ActionLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantObserver
{
    public function created(ProductVariant $variant): void
    {
        $actor = $this->getActor();
        $siteId = $this->resolveSiteId($variant);

        ActionLog::create([
            'actor_id' => $actor?->id,
            'site_id' => $siteId,
            'action_type' => ActionType::CREATE,
            'field_name' => 'product_variant',
            'old_value' => null,
            'new_value' => trim(($variant->title ?: 'Вариант').' '.($variant->sku ?: '')),
            'created_at' => now(),
        ]);
    }

    public function updated(ProductVariant $variant): void
    {
        $actor = $this->getActor();
        $siteId = $this->resolveSiteId($variant);

        foreach (['title', 'sku', 'price', 'purchase_price', 'is_active', 'attributes'] as $field) {
            if ($variant->wasChanged($field)) {
                $old = $variant->getOriginal($field);
                $new = $variant->$field;

                $oldStr = is_array($old) ? json_encode($old, JSON_UNESCAPED_UNICODE) : (is_bool($old) ? ($old ? 'true' : 'false') : (string) $old);
                $newStr = is_array($new) ? json_encode($new, JSON_UNESCAPED_UNICODE) : (is_bool($new) ? ($new ? 'true' : 'false') : (string) $new);

                ActionLog::create([
                    'actor_id' => $actor?->id,
                    'site_id' => $siteId,
                    'action_type' => $field === 'is_active' ? ActionType::STATUS_CHANGE : ActionType::UPDATE,
                    'field_name' => "product_variant.{$field}",
                    'old_value' => $oldStr,
                    'new_value' => $newStr,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function deleting(ProductVariant $variant): void
    {
        $actor = $this->getActor();
        $siteId = $this->resolveSiteId($variant);

        ActionLog::create([
            'actor_id' => $actor?->id,
            'site_id' => $siteId,
            'action_type' => ActionType::DELETE,
            'field_name' => 'product_variant',
            'old_value' => trim(($variant->title ?: 'Вариант').' '.($variant->sku ?: '')),
            'new_value' => null,
            'created_at' => now(),
        ]);
    }

    protected function resolveSiteId(ProductVariant $variant): ?int
    {
        if ($variant->relationLoaded('product')) {
            return $variant->product?->site_id;
        }

        if ($variant->product_id) {
            return Product::where('id', $variant->product_id)->value('site_id');
        }

        return null;
    }

    protected function getActor(): ?User
    {
        $user = request()?->attributes?->get('user') ?? auth()->user();

        return $user instanceof User ? $user : null;
    }
}
