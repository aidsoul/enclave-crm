<?php

namespace App\Observers;

use App\Enums\ActionType;
use App\Models\ActionLog;
use App\Models\Product;
use App\Models\User;
use App\Services\AnalyticsService;

class ProductObserver
{
    public function created(Product $product): void
    {
        $actor = $this->getActor();

        ActionLog::create([
            'actor_id' => $actor?->id,
            'site_id' => $product->site_id,
            'action_type' => ActionType::CREATE,
            'field_name' => 'product',
            'old_value' => null,
            'new_value' => "{$product->name} (Цена: {$product->price})",
            'created_at' => now(),
        ]);

        AnalyticsService::invalidateDashboardStatsCache();
    }

    public function updated(Product $product): void
    {
        $actor = $this->getActor();
        $invalidated = false;

        foreach (['name', 'sku', 'price', 'cost_price', 'is_active'] as $field) {
            if ($product->wasChanged($field)) {
                $old = $product->getOriginal($field);
                $new = $product->$field;
                ActionLog::create([
                    'actor_id' => $actor?->id,
                    'site_id' => $product->site_id,
                    'action_type' => $field === 'is_active' ? ActionType::STATUS_CHANGE : ActionType::UPDATE,
                    'field_name' => "product.{$field}",
                    'old_value' => (string) $old,
                    'new_value' => (string) $new,
                    'created_at' => now(),
                ]);

                if (! $invalidated) {
                    AnalyticsService::invalidateDashboardStatsCache();
                    $invalidated = true;
                }
            }
        }
    }

    public function deleting(Product $product): void
    {
        $actor = $this->getActor();

        ActionLog::create([
            'actor_id' => $actor?->id,
            'site_id' => $product->site_id,
            'action_type' => ActionType::DELETE,
            'field_name' => 'product',
            'old_value' => "{$product->name} (Цена: {$product->price})",
            'new_value' => null,
            'created_at' => now(),
        ]);

        AnalyticsService::invalidateDashboardStatsCache();
    }

    protected function getActor(): ?User
    {
        $user = request()?->attributes?->get('user') ?? auth()->user();

        return $user instanceof User ? $user : null;
    }
}
