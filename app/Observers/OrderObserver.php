<?php

namespace App\Observers;

use App\Enums\ActionType;
use App\Jobs\AggregateFinancialStatsJob;
use App\Models\ActionLog;
use App\Models\Order;
use App\Models\User;
use App\Services\AnalyticsService;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->logIfNeeded($order, ActionType::CREATE);

        AnalyticsService::invalidateDashboardStatsCache();
        AggregateFinancialStatsJob::dispatch($order->created_at->toDateString());
    }

    public function updated(Order $order): void
    {
        $changes = $order->getChanges();

        if (empty($changes)) {
            return;
        }

        if (! $order->skipAuditLog) {
            $actor = $this->getActor();
            $records = [];
            $now = now();
            foreach ($changes as $field => $newValue) {
                if ($field === 'updated_at') {
                    continue;
                }
                $oldValue = $order->getOriginal($field);
                $records[] = [
                    'actor_id' => $actor?->id,
                    'order_id' => $order->id,
                    'site_id' => $order->site_id,
                    'action_type' => $field === 'status' ? ActionType::STATUS_CHANGE->value : ActionType::UPDATE->value,
                    'field_name' => $field,
                    'old_value' => is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : (string) ($oldValue instanceof \BackedEnum ? $oldValue->value : $oldValue),
                    'new_value' => is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : (string) ($newValue instanceof \BackedEnum ? $newValue->value : $newValue),
                    'ip_address' => request()?->ip(),
                    'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
                    'created_at' => $now,
                ];
            }
            if (! empty($records)) {
                ActionLog::insert($records);
            }
        }

        $dirtyFields = array_keys($changes);
        $statsFieldsEdited = array_intersect(['total_price', 'purchase_price', 'status', 'site_id', 'created_at'], $dirtyFields);

        if (! empty($statsFieldsEdited)) {
            AnalyticsService::invalidateDashboardStatsCache();
            AggregateFinancialStatsJob::dispatch($order->created_at->toDateString());
        }
    }

    public function deleting(Order $order): void
    {
        $actor = $this->getActor();

        ActionLog::create([
            'actor_id' => $actor?->id,
            'order_id' => $order->id,
            'site_id' => $order->site_id,
            'action_type' => ActionType::DELETE,
            'field_name' => 'order',
            'old_value' => "Заказ #{$order->id} (Сумма: {$order->total_price})",
            'new_value' => null,
            'created_at' => now(),
        ]);

        AnalyticsService::invalidateDashboardStatsCache();
        AggregateFinancialStatsJob::dispatch($order->created_at->toDateString());
    }

    protected function getActor(): ?User
    {
        $user = request()?->attributes?->get('user') ?? auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected function logIfNeeded(Order $order, ActionType $type): void
    {
        if ($order->skipAuditLog) {
            return;
        }

        $actor = $this->getActor();

        ActionLog::create([
            'actor_id' => $actor?->id,
            'order_id' => $order->id,
            'site_id' => $order->site_id,
            'action_type' => $type,
            'field_name' => null,
            'old_value' => null,
            'new_value' => null,
            'created_at' => now(),
        ]);
    }
}
