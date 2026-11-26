<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Enums\LeadStatus;
use App\Enums\OrderStatus;
use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class AuditService
{
    public function paginateLogs(Request $request): LengthAwarePaginator
    {
        $query = ActionLog::with(['order.site', 'lead.site', 'actor', 'targetUser', 'customer', 'site'])
            ->orderByDesc('created_at');

        $filter = $request->query('filter');
        $orderNumber = $request->query('order_number') ?? $request->query('order_id');
        $customerId = $request->query('customer_id');
        $userId = $request->query('user_id') ?? $request->query('target_user_id');
        $siteId = $request->query('site_id');

        if ($filter === 'creates') {
            $query->where('action_type', ActionType::CREATE);
        } elseif ($filter === 'updates') {
            $query->where('action_type', ActionType::UPDATE);
        } elseif ($filter === 'statuses') {
            $query->where('action_type', ActionType::STATUS_CHANGE);
        } elseif ($filter === 'auth') {
            $query->where('action_type', ActionType::AUTH);
        } elseif ($filter === 'exports') {
            $query->where('action_type', ActionType::EXPORT);
        } elseif ($filter === 'edited') {
            $query->where('action_type', ActionType::UPDATE)
                ->whereIn('field_name', ['total_price', 'purchase_price']);
        }

        if ($orderNumber !== null && $orderNumber !== '') {
            $num = (int) $orderNumber;
            $query->where(function ($q) use ($num) {
                $q->where('order_id', $num)
                    ->orWhere('lead_id', $num);
            });
        }

        if ($customerId !== null && $customerId !== '') {
            $query->where('customer_id', (int) $customerId);
        }

        if ($userId !== null && $userId !== '') {
            $uId = (int) $userId;
            $query->where(function ($q) use ($uId) {
                $q->where('target_user_id', $uId)
                    ->orWhere('actor_id', $uId);
            });
        }

        if ($siteId !== null && $siteId !== '') {
            $query->where('site_id', (int) $siteId);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage);

        $this->formatLogs($paginator->getCollection());

        return $paginator;
    }

    public function getOrderLogs(int $orderId): Collection
    {
        $logs = ActionLog::with(['actor', 'order.site'])
            ->where('order_id', $orderId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $this->formatLogs($logs);

        return $logs;
    }

    public function getLog(int $id): ActionLog
    {
        $log = ActionLog::with(['actor', 'order.site', 'lead.site', 'targetUser', 'customer', 'site'])->findOrFail($id);
        $this->formatLogs([$log]);

        return $log;
    }

    public function formatLogs(iterable $logs): void
    {
        $userIds = [];
        foreach ($logs as $log) {
            if (in_array($log->field_name, ['courier_id', 'delivery.user_id', 'manager_id'], true)) {
                if ($log->old_value && ctype_digit((string) $log->old_value)) {
                    $userIds[] = (int) $log->old_value;
                }
                if ($log->new_value && ctype_digit((string) $log->new_value)) {
                    $userIds[] = (int) $log->new_value;
                }
            }
        }

        $userMap = ! empty($userIds)
            ? User::whereIn('id', array_unique($userIds))->pluck('system_name', 'id')->all()
            : [];

        foreach ($logs as $log) {
            $this->formatLogDisplay($log, $userMap);
        }
    }

    protected function formatLogDisplay(ActionLog $log, array $userMap = []): void
    {
        if (in_array($log->field_name, ['courier_id', 'delivery.user_id', 'manager_id'], true)) {
            $log->old_value_display = ($log->old_value && isset($userMap[(int) $log->old_value]))
                ? $userMap[(int) $log->old_value]
                : ($log->old_value ?: 'Не назначен');
            $log->new_value_display = ($log->new_value && isset($userMap[(int) $log->new_value]))
                ? $userMap[(int) $log->new_value]
                : ($log->new_value ?: 'Не назначен');
        } elseif ($log->field_name === 'status') {
            $log->old_value_display = $log->old_value ? (OrderStatus::tryFrom($log->old_value)?->label() ?? LeadStatus::tryFrom($log->old_value)?->label() ?? $log->old_value) : '—';
            $log->new_value_display = $log->new_value ? (OrderStatus::tryFrom($log->new_value)?->label() ?? LeadStatus::tryFrom($log->new_value)?->label() ?? $log->new_value) : '—';
        } elseif (in_array($log->field_name, ['total_price', 'purchase_price', 'delivery.cost', 'delivery.price', 'payment.amount'], true)) {
            $currency = config('app.currency_symbol', 'Br');
            $log->old_value_display = $log->old_value !== null ? number_format((float) $log->old_value, 2).' '.$currency : '—';
            $log->new_value_display = $log->new_value !== null ? number_format((float) $log->new_value, 2).' '.$currency : '—';
        } elseif (in_array($log->field_name, ['is_active', 'product.is_active', 'product_variant.is_active'], true)) {
            $log->old_value_display = in_array($log->old_value, ['1', 'true', 'Активен'], true) ? 'Активен' : 'Отключён';
            $log->new_value_display = in_array($log->new_value, ['1', 'true', 'Активен'], true) ? 'Активен' : 'Отключён';
        } elseif ($log->field_name === 'is_blacklisted') {
            $log->old_value_display = ($log->old_value === '1' || $log->old_value === 'true' || $log->old_value === 'В чёрном списке') ? 'В чёрном списке' : 'Не в ЧС';
            $log->new_value_display = ($log->new_value === '1' || $log->new_value === 'true' || $log->new_value === 'В чёрном списке') ? 'В чёрном списке' : 'Не в ЧС';
        } elseif ($log->field_name === 'is_valuable') {
            $log->old_value_display = in_array((string) $log->old_value, ['1', 'true', 'Ценное'], true) ? 'Ценное' : 'Обычное';
            $log->new_value_display = in_array((string) $log->new_value, ['1', 'true', 'Ценное'], true) ? 'Ценное' : 'Обычное';
        }
    }
}
