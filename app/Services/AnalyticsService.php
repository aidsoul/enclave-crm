<?php

namespace App\Services;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\ActionLog;
use App\Models\DailyUserKpi;
use App\Models\Delivery;
use App\Models\FinancialStat;
use App\Models\Order;
use App\Models\User;
use App\Support\StrHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function resolveDateRange(?string $period = 'today', ?string $from = null, ?string $to = null, ?string $date = null): array
    {
        if ($date) {
            $d = Carbon::parse($date);

            return [$d->copy()->startOfDay(), $d->copy()->endOfDay()];
        }

        if ($from && $to) {
            $startDate = Carbon::parse($from)->startOfDay();
            $endDate = Carbon::parse($to)->endOfDay();
            if ($startDate->gt($endDate)) {
                [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
            }

            return [$startDate, $endDate];
        }

        $now = now();

        return match ($period) {
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'all' => [
                Cache::remember('analytics_earliest_date', 3600, function () {
                    $minOrder = Order::min('created_at');

                    return $minOrder ? Carbon::parse($minOrder)->startOfDay() : Carbon::create(2020, 1, 1)->startOfDay();
                }),
                $now->copy()->endOfDay(),
            ],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    public function calculateManagersRaw(Carbon $startDate, Carbon $endDate, $managers, ?int $managerId = null): array
    {
        $ordersQuery = Order::whereNotNull('manager_id')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($managerId) {
            $ordersQuery->where('manager_id', $managerId);
        }

        $orders = $ordersQuery->get(['id', 'manager_id', 'status', 'created_at', 'updated_at']);
        $orderIds = $orders->pluck('id');

        $firstActionLogs = $orderIds->isNotEmpty()
            ? ActionLog::whereIn('order_id', $orderIds)
                ->where(function ($q) {
                    $q->where('field_name', 'manager_id')
                        ->orWhere(function ($q2) {
                            $q2->where('field_name', 'status')
                                ->where('new_value', OrderStatus::ACCEPTED->value);
                        });
                })
                ->groupBy('order_id')
                ->selectRaw('order_id, MIN(created_at) as first_action_at')
                ->pluck('first_action_at', 'order_id')
            : collect();

        $ordersByManager = $orders->groupBy('manager_id');
        $stats = [];

        foreach ($managers as $manager) {
            $mOrders = $ordersByManager->get($manager->id, collect());
            $acceptedCount = $mOrders->count();
            $completedCount = 0;
            $cancelledCount = 0;
            $inProgressCount = 0;
            $totalResponseTime = 0;

            foreach ($mOrders as $ord) {
                if ($ord->status === OrderStatus::COMPLETED) {
                    $completedCount++;
                } elseif ($ord->status === OrderStatus::CANCELLED) {
                    $cancelledCount++;
                } elseif (in_array($ord->status, [
                    OrderStatus::ACCEPTED,
                    OrderStatus::ASSIGNED,
                    OrderStatus::AT_WAREHOUSE,
                ], true)) {
                    $inProgressCount++;
                }

                $firstLogAt = $firstActionLogs->get($ord->id);
                $diff = $firstLogAt ? max(0, (int) $ord->created_at->diffInSeconds(Carbon::parse($firstLogAt), false)) : 0;
                $totalResponseTime += $diff;
            }

            $stats[$manager->id] = [
                'accepted_orders' => $acceptedCount,
                'completed_orders' => $completedCount,
                'cancelled_orders' => $cancelledCount,
                'in_progress_orders' => $inProgressCount,
                'total_response_time_seconds' => $totalResponseTime,
                'response_orders_count' => $acceptedCount,
            ];
        }

        return $stats;
    }

    public function calculateCouriersRaw(Carbon $startDate, Carbon $endDate, $couriers, ?int $courierId = null): array
    {
        $completedOrderIdsFromLogs = ActionLog::where('field_name', 'status')
            ->where('new_value', OrderStatus::COMPLETED->value)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->pluck('order_id')
            ->unique();

        $ordersQuery = Order::where('status', OrderStatus::COMPLETED)
            ->whereHas('delivery', function ($q) use ($courierId) {
                $q->whereNotNull('user_id');
                if ($courierId) {
                    $q->where('user_id', $courierId);
                }
            })
            ->where(function ($q) use ($startDate, $endDate, $completedOrderIdsFromLogs) {
                if ($completedOrderIdsFromLogs->isNotEmpty()) {
                    $q->whereIn('id', $completedOrderIdsFromLogs)
                        ->orWhereBetween('updated_at', [$startDate, $endDate])
                        ->orWhereHas('delivery', fn ($dq) => $dq->whereBetween('delivery_date', [$startDate->toDateString(), $endDate->toDateString()]));
                } else {
                    $q->whereBetween('updated_at', [$startDate, $endDate])
                        ->orWhereHas('delivery', fn ($dq) => $dq->whereBetween('delivery_date', [$startDate->toDateString(), $endDate->toDateString()]));
                }
            })
            ->with([
                'delivery:id,order_id,user_id,delivery_cost',
                'payment:id,order_id,amount,method,status',
            ]);

        $orders = $ordersQuery->get(['id', 'status', 'total_price', 'updated_at']);

        $ordersByCourier = [];
        foreach ($orders as $order) {
            foreach ($order->delivery as $del) {
                if ($del->user_id) {
                    $ordersByCourier[$del->user_id][] = $order;
                }
            }
        }

        $transfersOutByCourier = collect();
        $transfersInByCourier = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('courier_handovers')) {
            $courierIds = $couriers->pluck('id');
            if ($courierIds->isNotEmpty()) {
                $transfersOutByCourier = \App\Models\CourierHandover::whereIn('from_courier_id', $courierIds)
                    ->where('transfer_cash', true)
                    ->whereIn('status', [\App\Enums\CourierHandoverStatus::COMPLETED, \App\Enums\CourierHandoverStatus::DISPUTED])
                    ->whereBetween('completed_at', [$startDate, $endDate])
                    ->groupBy('from_courier_id')
                    ->selectRaw('from_courier_id, SUM(actual_cash_amount) as total')
                    ->pluck('total', 'from_courier_id');

                $transfersInByCourier = \App\Models\CourierHandover::whereIn('to_courier_id', $courierIds)
                    ->where('transfer_cash', true)
                    ->whereIn('status', [\App\Enums\CourierHandoverStatus::COMPLETED, \App\Enums\CourierHandoverStatus::DISPUTED])
                    ->whereBetween('completed_at', [$startDate, $endDate])
                    ->groupBy('to_courier_id')
                    ->selectRaw('to_courier_id, SUM(actual_cash_amount) as total')
                    ->pluck('total', 'to_courier_id');
            }
        }

        $stats = [];
        foreach ($couriers as $courier) {
            $cOrders = $ordersByCourier[$courier->id] ?? [];
            $completedCount = count($cOrders);
            $courierCash = 0.0;
            $courierTerminal = 0.0;
            $courierOther = 0.0;
            $courierPayout = 0.0;

            foreach ($cOrders as $ord) {
                $delivery = $ord->delivery->firstWhere('user_id', $courier->id) ?? $ord->delivery->first();
                if ($delivery) {
                    $courierPayout += (float) ($delivery->delivery_cost ?? 0);
                }

                $paidPayments = $ord->payment->where('status', PaymentStatus::PAID);
                if ($paidPayments->isNotEmpty()) {
                    foreach ($paidPayments as $p) {
                        $amt = (float) $p->amount;
                        if ($p->method === PaymentMethod::CASH) {
                            $courierCash += $amt;
                        } elseif ($p->method === PaymentMethod::TERMINAL) {
                            $courierTerminal += $amt;
                        } else {
                            $courierOther += $amt;
                        }
                    }
                } else {
                    $courierCash += (float) $ord->total_price;
                }
            }

            $transferredOut = (float) ($transfersOutByCourier->get($courier->id) ?? 0);
            $transferredIn = (float) ($transfersInByCourier->get($courier->id) ?? 0);
            $courierCash = max(0.0, $courierCash - $transferredOut + $transferredIn);

            $cCollected = $courierCash + $courierTerminal + $courierOther;

            $stats[$courier->id] = [
                'completed_deliveries' => $completedCount,
                'total_collected' => round($cCollected, 2),
                'cash_amount' => round($courierCash, 2),
                'cash_transferred_out' => round($transferredOut, 2),
                'cash_transferred_in' => round($transferredIn, 2),
                'terminal_amount' => round($courierTerminal, 2),
                'other_amount' => round($courierOther, 2),
                'payout_amount' => round($courierPayout, 2),
            ];
        }

        return $stats;
    }

    public function getManagerMetrics(
        ?string $period = 'today',
        ?string $from = null,
        ?string $to = null,
        ?string $date = null,
        ?int $managerId = null,
        ?string $search = null,
        ?string $filter = null,
        ?string $sort = null,
        ?int $page = null,
        ?int $perPage = null
    ): array {
        [$startDate, $endDate] = $this->resolveDateRange($period, $from, $to, $date);

        $managersQuery = User::whereIn('role', [UserRole::MANAGER, UserRole::OWNER]);
        if ($managerId) {
            $managersQuery->where('id', $managerId);
        } else {
            $managersQuery->where(function ($q) use ($startDate, $endDate) {
                $q->where('is_active', true)
                    ->orWhereHas('orders', fn ($oq) => $oq->whereBetween('created_at', [$startDate, $endDate]))
                    ->orWhereHas('dailyKpis', fn ($kq) => $kq->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]));
            });
        }
        $managers = $managersQuery->orderBy('system_name')->get();

        $today = Carbon::today();
        $histStart = $startDate->copy()->startOfDay();
        $histEnd = $endDate->copy()->lt($today) ? $endDate->copy()->endOfDay() : $today->copy()->subSecond();

        $hasHistoricalRange = $histStart->lte($histEnd);
        $hasHistoricalData = false;

        if ($hasHistoricalRange) {
            $totalHistDays = (int) $histStart->copy()->startOfDay()->diffInDays($histEnd->copy()->startOfDay()) + 1;
            $aggregatedDaysCount = DailyUserKpi::whereBetween('date', [$histStart->toDateString(), $histEnd->toDateString()])
                ->distinct('date')
                ->count('date');

            $hasHistoricalData = ($aggregatedDaysCount >= $totalHistDays);
        }

        $managerStats = [];
        $totalTeamAccepted = 0;
        $totalTeamCompleted = 0;
        $totalTeamCancelled = 0;
        $totalTeamResponseTime = 0;
        $totalTeamResponseOrders = 0;

        if ($hasHistoricalData) {
            $histQuery = DailyUserKpi::whereBetween('date', [$histStart->toDateString(), $histEnd->toDateString()])
                ->selectRaw('
                    user_id,
                    SUM(accepted_orders) as accepted_orders,
                    SUM(completed_orders) as completed_orders,
                    SUM(cancelled_orders) as cancelled_orders,
                    SUM(in_progress_orders) as in_progress_orders,
                    SUM(total_response_time_seconds) as total_response_time_seconds,
                    SUM(response_orders_count) as response_orders_count
                ')
                ->groupBy('user_id');

            if ($managerId) {
                $histQuery->where('user_id', $managerId);
            }
            $histStats = $histQuery->get()->keyBy('user_id');

            $todayStats = [];
            if ($endDate->gte($today)) {
                $todayStats = $this->calculateManagersRaw($today->copy()->startOfDay(), $endDate, $managers, $managerId);
            }

            foreach ($managers as $manager) {
                $hist = $histStats->get($manager->id);
                $tod = $todayStats[$manager->id] ?? null;

                $acceptedCount = (int) (($hist->accepted_orders ?? 0) + ($tod['accepted_orders'] ?? 0));
                $completedCount = (int) (($hist->completed_orders ?? 0) + ($tod['completed_orders'] ?? 0));
                $cancelledCount = (int) (($hist->cancelled_orders ?? 0) + ($tod['cancelled_orders'] ?? 0));
                $inProgressCount = (int) (($hist->in_progress_orders ?? 0) + ($tod['in_progress_orders'] ?? 0));

                $totalRespSeconds = (int) (($hist->total_response_time_seconds ?? 0) + ($tod['total_response_time_seconds'] ?? 0));
                $respOrdersCount = (int) (($hist->response_orders_count ?? 0) + ($tod['response_orders_count'] ?? 0));

                $avgSeconds = $respOrdersCount > 0 ? (int) round($totalRespSeconds / $respOrdersCount) : null;
                $conversionRate = $acceptedCount > 0 ? round(($completedCount / $acceptedCount) * 100, 1) : 0.0;

                $totalTeamAccepted += $acceptedCount;
                $totalTeamCompleted += $completedCount;
                $totalTeamCancelled += $cancelledCount;
                $totalTeamResponseTime += $totalRespSeconds;
                $totalTeamResponseOrders += $respOrdersCount;

                $managerStats[] = [
                    'id' => $manager->id,
                    'name' => $manager->system_name,
                    'role' => $manager->role->value,
                    'accepted_orders' => $acceptedCount,
                    'completed_orders' => $completedCount,
                    'cancelled_orders' => $cancelledCount,
                    'in_progress_orders' => $inProgressCount,
                    'conversion_rate' => $conversionRate,
                    'avg_response_time_seconds' => $avgSeconds,
                    'avg_response_time_formatted' => $avgSeconds !== null ? $this->formatDuration($avgSeconds) : '—',
                ];
            }
        } else {
            $rawStats = $this->calculateManagersRaw($startDate, $endDate, $managers, $managerId);

            foreach ($managers as $manager) {
                $raw = $rawStats[$manager->id] ?? [];
                $acceptedCount = (int) ($raw['accepted_orders'] ?? 0);
                $completedCount = (int) ($raw['completed_orders'] ?? 0);
                $cancelledCount = (int) ($raw['cancelled_orders'] ?? 0);
                $inProgressCount = (int) ($raw['in_progress_orders'] ?? 0);
                $totalRespSeconds = (int) ($raw['total_response_time_seconds'] ?? 0);
                $respOrdersCount = (int) ($raw['response_orders_count'] ?? 0);

                $avgSeconds = $respOrdersCount > 0 ? (int) round($totalRespSeconds / $respOrdersCount) : null;
                $conversionRate = $acceptedCount > 0 ? round(($completedCount / $acceptedCount) * 100, 1) : 0.0;

                $totalTeamAccepted += $acceptedCount;
                $totalTeamCompleted += $completedCount;
                $totalTeamCancelled += $cancelledCount;
                $totalTeamResponseTime += $totalRespSeconds;
                $totalTeamResponseOrders += $respOrdersCount;

                $managerStats[] = [
                    'id' => $manager->id,
                    'name' => $manager->system_name,
                    'role' => $manager->role->value,
                    'accepted_orders' => $acceptedCount,
                    'completed_orders' => $completedCount,
                    'cancelled_orders' => $cancelledCount,
                    'in_progress_orders' => $inProgressCount,
                    'conversion_rate' => $conversionRate,
                    'avg_response_time_seconds' => $avgSeconds,
                    'avg_response_time_formatted' => $avgSeconds !== null ? $this->formatDuration($avgSeconds) : '—',
                ];
            }
        }

        $teamAvgSeconds = $totalTeamResponseOrders > 0 ? (int) round($totalTeamResponseTime / $totalTeamResponseOrders) : null;
        $teamConversionRate = $totalTeamAccepted > 0 ? round(($totalTeamCompleted / $totalTeamAccepted) * 100, 1) : 0.0;

        // Search filter
        if ($search && trim($search) !== '') {
            $q = mb_strtolower(trim($search));
            $managerStats = array_values(array_filter($managerStats, function ($m) use ($q) {
                return mb_stripos($m['name'] ?? '', $q) !== false;
            }));
        }

        // Status filter
        if ($filter === 'with_orders') {
            $managerStats = array_values(array_filter($managerStats, fn ($m) => ($m['accepted_orders'] ?? 0) > 0));
        } elseif ($filter === 'without_orders') {
            $managerStats = array_values(array_filter($managerStats, fn ($m) => ($m['accepted_orders'] ?? 0) === 0));
        } elseif ($filter === 'with_completed') {
            $managerStats = array_values(array_filter($managerStats, fn ($m) => ($m['completed_orders'] ?? 0) > 0));
        }

        // Sorting
        usort($managerStats, function ($a, $b) use ($sort) {
            switch ($sort) {
                case 'orders_asc':
                    return ($a['accepted_orders'] <=> $b['accepted_orders']) ?: strcmp($a['name'], $b['name']);
                case 'conversion_desc':
                    $hasA = ($a['accepted_orders'] ?? 0) > 0;
                    $hasB = ($b['accepted_orders'] ?? 0) > 0;
                    if ($hasA !== $hasB) {
                        return $hasA ? -1 : 1;
                    }

                    return ($b['conversion_rate'] <=> $a['conversion_rate']) ?: ($b['accepted_orders'] <=> $a['accepted_orders']);
                case 'conversion_asc':
                    $hasA = ($a['accepted_orders'] ?? 0) > 0;
                    $hasB = ($b['accepted_orders'] ?? 0) > 0;
                    if ($hasA !== $hasB) {
                        return $hasA ? -1 : 1;
                    }

                    return ($a['conversion_rate'] <=> $b['conversion_rate']) ?: ($a['accepted_orders'] <=> $b['accepted_orders']);
                case 'speed_fast':
                    $hasA = $a['avg_response_time_seconds'] !== null;
                    $hasB = $b['avg_response_time_seconds'] !== null;
                    if ($hasA !== $hasB) {
                        return $hasA ? -1 : 1;
                    }

                    return ($a['avg_response_time_seconds'] <=> $b['avg_response_time_seconds']) ?: ($b['accepted_orders'] <=> $a['accepted_orders']);
                case 'speed_slow':
                    $hasA = $a['avg_response_time_seconds'] !== null;
                    $hasB = $b['avg_response_time_seconds'] !== null;
                    if ($hasA !== $hasB) {
                        return $hasA ? -1 : 1;
                    }

                    return ($b['avg_response_time_seconds'] <=> $a['avg_response_time_seconds']) ?: ($b['accepted_orders'] <=> $a['accepted_orders']);
                case 'name_asc':
                    return strcmp($a['name'], $b['name']);
                case 'name_desc':
                    return strcmp($b['name'], $a['name']);
                case 'orders_desc':
                default:
                    if ($a['accepted_orders'] !== $b['accepted_orders']) {
                        return $b['accepted_orders'] <=> $a['accepted_orders'];
                    }

                    return strcmp($a['name'], $b['name']);
            }
        });

        $totalFiltered = count($managerStats);

        if ($perPage !== null && $perPage > 0) {
            $page = max(1, (int) ($page ?? 1));
            $offset = ($page - 1) * $perPage;
            $pagedManagers = array_slice($managerStats, $offset, $perPage);
            $hasMore = ($offset + count($pagedManagers)) < $totalFiltered;
            $remaining = max(0, $totalFiltered - ($offset + count($pagedManagers)));
        } else {
            $pagedManagers = $managerStats;
            $page = 1;
            $perPage = $totalFiltered;
            $hasMore = false;
            $remaining = 0;
        }

        return [
            'period' => $period,
            'date_from' => $startDate->toIso8601String(),
            'date_to' => $endDate->toIso8601String(),
            'summary' => [
                'total_accepted' => $totalTeamAccepted,
                'total_completed' => $totalTeamCompleted,
                'total_cancelled' => $totalTeamCancelled,
                'conversion_rate' => $teamConversionRate,
                'avg_response_time_seconds' => $teamAvgSeconds,
                'avg_response_time_formatted' => $teamAvgSeconds !== null ? $this->formatDuration($teamAvgSeconds) : '—',
            ],
            'pagination' => [
                'total' => $totalFiltered,
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'remaining' => $remaining,
            ],
            'managers' => $pagedManagers,
        ];
    }

    public function getCourierMetrics(
        ?string $period = 'today',
        ?string $from = null,
        ?string $to = null,
        ?string $date = null,
        ?int $courierId = null,
        ?string $search = null,
        ?string $filter = null,
        ?string $sort = null,
        ?int $page = null,
        ?int $perPage = null
    ): array {
        [$startDate, $endDate] = $this->resolveDateRange($period, $from, $to, $date);

        $couriersQuery = User::where('role', UserRole::COURIER);
        if ($courierId) {
            $couriersQuery->where('id', $courierId);
        } else {
            $couriersQuery->where(function ($q) use ($startDate, $endDate) {
                $q->where('is_active', true)
                    ->orWhereHas('deliveries', fn ($dq) => $dq->where(function ($dq2) use ($startDate, $endDate) {
                        $dq2->whereBetween('delivery_date', [$startDate->toDateString(), $endDate->toDateString()])
                            ->orWhereBetween('created_at', [$startDate, $endDate]);
                    }))
                    ->orWhereHas('dailyKpis', fn ($kq) => $kq->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]));
            });
        }
        $couriers = $couriersQuery->orderBy('system_name')->get();

        $today = Carbon::today();
        $histStart = $startDate->copy()->startOfDay();
        $histEnd = $endDate->copy()->lt($today) ? $endDate->copy()->endOfDay() : $today->copy()->subSecond();

        $hasHistoricalRange = $histStart->lte($histEnd);
        $hasHistoricalData = false;

        if ($hasHistoricalRange) {
            $totalHistDays = (int) $histStart->copy()->startOfDay()->diffInDays($histEnd->copy()->startOfDay()) + 1;
            $aggregatedDaysCount = DailyUserKpi::whereBetween('date', [$histStart->toDateString(), $histEnd->toDateString()])
                ->distinct('date')
                ->count('date');

            $hasHistoricalData = ($aggregatedDaysCount >= $totalHistDays);
        }

        $courierStats = [];
        $totalDeliveries = 0;
        $totalCollected = 0.0;
        $totalCash = 0.0;
        $totalTerminal = 0.0;
        $totalPayouts = 0.0;

        if ($hasHistoricalData) {
            $histQuery = DailyUserKpi::whereBetween('date', [$histStart->toDateString(), $histEnd->toDateString()])
                ->selectRaw('
                    user_id,
                    SUM(completed_deliveries) as completed_deliveries,
                    SUM(total_collected) as total_collected,
                    SUM(cash_amount) as cash_amount,
                    SUM(terminal_amount) as terminal_amount,
                    SUM(other_amount) as other_amount,
                    SUM(payout_amount) as payout_amount
                ')
                ->groupBy('user_id');

            if ($courierId) {
                $histQuery->where('user_id', $courierId);
            }
            $histStats = $histQuery->get()->keyBy('user_id');

            $todayStats = [];
            if ($endDate->gte($today)) {
                $todayStats = $this->calculateCouriersRaw($today->copy()->startOfDay(), $endDate, $couriers, $courierId);
            }

            foreach ($couriers as $courier) {
                $hist = $histStats->get($courier->id);
                $tod = $todayStats[$courier->id] ?? null;

                $completedCount = (int) (($hist->completed_deliveries ?? 0) + ($tod['completed_deliveries'] ?? 0));
                $cCash = (float) (($hist->cash_amount ?? 0) + ($tod['cash_amount'] ?? 0));
                $cTerminal = (float) (($hist->terminal_amount ?? 0) + ($tod['terminal_amount'] ?? 0));
                $cOther = (float) (($hist->other_amount ?? 0) + ($tod['other_amount'] ?? 0));
                $cCollected = (float) (($hist->total_collected ?? 0) + ($tod['total_collected'] ?? 0));
                $cPayout = (float) (($hist->payout_amount ?? 0) + ($tod['payout_amount'] ?? 0));

                $totalDeliveries += $completedCount;
                $totalCollected += $cCollected;
                $totalCash += $cCash;
                $totalTerminal += $cTerminal;
                $totalPayouts += $cPayout;

                $courierStats[] = [
                    'id' => $courier->id,
                    'name' => $courier->system_name,
                    'completed_deliveries' => $completedCount,
                    'total_collected' => round($cCollected, 2),
                    'cash_amount' => round($cCash, 2),
                    'cash_transferred_out' => round((float) ($tod['cash_transferred_out'] ?? 0), 2),
                    'cash_transferred_in' => round((float) ($tod['cash_transferred_in'] ?? 0), 2),
                    'terminal_amount' => round($cTerminal, 2),
                    'other_amount' => round($cOther, 2),
                    'payout_amount' => round($cPayout, 2),
                ];
            }
        } else {
            $rawStats = $this->calculateCouriersRaw($startDate, $endDate, $couriers, $courierId);

            foreach ($couriers as $courier) {
                $raw = $rawStats[$courier->id] ?? [];
                $completedCount = (int) ($raw['completed_deliveries'] ?? 0);
                $cCollected = (float) ($raw['total_collected'] ?? 0);
                $cCash = (float) ($raw['cash_amount'] ?? 0);
                $cTerminal = (float) ($raw['terminal_amount'] ?? 0);
                $cOther = (float) ($raw['other_amount'] ?? 0);
                $cPayout = (float) ($raw['payout_amount'] ?? 0);

                $totalDeliveries += $completedCount;
                $totalCollected += $cCollected;
                $totalCash += $cCash;
                $totalTerminal += $cTerminal;
                $totalPayouts += $cPayout;

                $courierStats[] = [
                    'id' => $courier->id,
                    'name' => $courier->system_name,
                    'completed_deliveries' => $completedCount,
                    'total_collected' => round($cCollected, 2),
                    'cash_amount' => round($cCash, 2),
                    'cash_transferred_out' => round((float) ($raw['cash_transferred_out'] ?? 0), 2),
                    'cash_transferred_in' => round((float) ($raw['cash_transferred_in'] ?? 0), 2),
                    'terminal_amount' => round($cTerminal, 2),
                    'other_amount' => round($cOther, 2),
                    'payout_amount' => round($cPayout, 2),
                ];
            }
        }

        // Search filter
        if ($search && trim($search) !== '') {
            $q = mb_strtolower(trim($search));
            $courierStats = array_values(array_filter($courierStats, function ($c) use ($q) {
                return mb_stripos($c['name'] ?? '', $q) !== false;
            }));
        }

        // Status filter
        if ($filter === 'with_deliveries') {
            $courierStats = array_values(array_filter($courierStats, fn ($c) => ($c['completed_deliveries'] ?? 0) > 0));
        } elseif ($filter === 'without_deliveries') {
            $courierStats = array_values(array_filter($courierStats, fn ($c) => ($c['completed_deliveries'] ?? 0) === 0));
        }

        // Sorting
        usort($courierStats, function ($a, $b) use ($sort) {
            switch ($sort) {
                case 'deliveries_asc':
                    return ($a['completed_deliveries'] <=> $b['completed_deliveries']) ?: strcmp($a['name'], $b['name']);
                case 'collected_desc':
                    return ($b['total_collected'] <=> $a['total_collected']) ?: ($b['completed_deliveries'] <=> $a['completed_deliveries']);
                case 'collected_asc':
                    return ($a['total_collected'] <=> $b['total_collected']) ?: ($a['completed_deliveries'] <=> $b['completed_deliveries']);
                case 'payout_desc':
                    return ($b['payout_amount'] <=> $a['payout_amount']) ?: ($b['completed_deliveries'] <=> $a['completed_deliveries']);
                case 'name_asc':
                    return strcmp($a['name'], $b['name']);
                case 'name_desc':
                    return strcmp($b['name'], $a['name']);
                case 'deliveries_desc':
                default:
                    if ($a['completed_deliveries'] !== $b['completed_deliveries']) {
                        return $b['completed_deliveries'] <=> $a['completed_deliveries'];
                    }

                    return strcmp($a['name'], $b['name']);
            }
        });

        $totalFiltered = count($courierStats);

        if ($perPage !== null && $perPage > 0) {
            $page = max(1, (int) ($page ?? 1));
            $offset = ($page - 1) * $perPage;
            $pagedCouriers = array_slice($courierStats, $offset, $perPage);
            $hasMore = ($offset + count($pagedCouriers)) < $totalFiltered;
            $remaining = max(0, $totalFiltered - ($offset + count($pagedCouriers)));
        } else {
            $pagedCouriers = $courierStats;
            $page = 1;
            $perPage = $totalFiltered;
            $hasMore = false;
            $remaining = 0;
        }

        return [
            'period' => $period,
            'date_from' => $startDate->toIso8601String(),
            'date_to' => $endDate->toIso8601String(),
            'summary' => [
                'total_deliveries' => $totalDeliveries,
                'total_collected' => round($totalCollected, 2),
                'cash_amount' => round($totalCash, 2),
                'terminal_amount' => round($totalTerminal, 2),
                'payout_amount' => round($totalPayouts, 2),
            ],
            'pagination' => [
                'total' => $totalFiltered,
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'remaining' => $remaining,
            ],
            'couriers' => $pagedCouriers,
        ];
    }

    public function getProductMetrics(
        ?string $period = 'today',
        ?string $from = null,
        ?string $to = null,
        ?string $date = null,
        ?int $siteId = null,
        ?string $search = null,
        ?string $filter = null,
        ?string $sort = null,
        ?int $page = null,
        ?int $perPage = null
    ): array {
        [$startDate, $endDate] = $this->resolveDateRange($period, $from, $to, $date);

        $perPage = min(max((int) ($perPage ?? 20), 1), 100);
        $page = max(1, (int) ($page ?? 1));

        $version = (int) Cache::get('dashboard_stats_version', 1);
        $searchKey = $search ? md5(trim($search)) : 'none';
        $cacheKey = "products_kpi_v{$version}_{$period}_{$from}_{$to}_{$date}_{$siteId}_{$searchKey}_{$filter}_{$sort}_{$page}_{$perPage}";
        $ttl = ($period === 'today' && ! $date && ! $from) ? 30 : 300;

        return Cache::remember($cacheKey, $ttl, function () use (
            $startDate, $endDate, $period, $siteId, $search, $filter, $sort, $page, $perPage
        ) {
            $salesSubquery = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
                ->whereBetween('o.created_at', [$startDate, $endDate])
                ->whereNotNull('oi.product_id');

            if ($siteId) {
                $salesSubquery->where('o.site_id', $siteId);
            }

            $salesSubquery->selectRaw('
                oi.product_id,
                COUNT(DISTINCT CASE WHEN o.status != ? THEN o.id END) as orders_count,
                COALESCE(SUM(CASE WHEN o.status != ? THEN oi.qty ELSE 0 END), 0) as total_qty,
                COALESCE(SUM(CASE WHEN o.status = ? THEN oi.qty ELSE 0 END), 0) as completed_qty,
                COALESCE(SUM(CASE WHEN o.status = ? THEN oi.qty ELSE 0 END), 0) as cancelled_qty,
                COALESCE(SUM(CASE WHEN o.status != ? THEN (oi.price * oi.qty) ELSE 0 END), 0) as revenue,
                COALESCE(SUM(CASE WHEN o.status != ? THEN ((CASE WHEN oi.purchase_price > 0 THEN oi.purchase_price ELSE COALESCE(p.cost_price, 0) END) * oi.qty) ELSE 0 END), 0) as total_purchase,
                COALESCE(SUM(CASE WHEN o.status != ? THEN ((oi.price - (CASE WHEN oi.purchase_price > 0 THEN oi.purchase_price ELSE COALESCE(p.cost_price, 0) END)) * oi.qty) ELSE 0 END), 0) as profit
            ', [
                OrderStatus::CANCELLED->value,
                OrderStatus::CANCELLED->value,
                OrderStatus::COMPLETED->value,
                OrderStatus::CANCELLED->value,
                OrderStatus::CANCELLED->value,
                OrderStatus::CANCELLED->value,
                OrderStatus::CANCELLED->value,
            ])
                ->groupBy('oi.product_id');

            $summaryRow = DB::query()->fromSub(clone $salesSubquery, 's')
                ->selectRaw('
                    COALESCE(SUM(s.revenue), 0) as total_revenue,
                    COALESCE(SUM(s.profit), 0) as total_profit,
                    COALESCE(SUM(s.completed_qty), 0) as total_completed_qty,
                    COALESCE(SUM(s.total_qty), 0) as total_qty,
                    COUNT(DISTINCT CASE WHEN s.completed_qty > 0 OR s.total_qty > 0 THEN s.product_id END) as unique_products_sold
                ')
                ->first();

            $totalRevenue = (float) ($summaryRow->total_revenue ?? 0);
            $totalProfit = (float) ($summaryRow->total_profit ?? 0);
            $avgMarginRate = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0.0;

            $productsQuery = DB::table('products as p')
                ->leftJoin('sites as s', 's.id', '=', 'p.site_id')
                ->leftJoinSub($salesSubquery, 'sales', function ($join) {
                    $join->on('sales.product_id', '=', 'p.id');
                })
                ->selectRaw('
                    p.id,
                    p.name,
                    p.sku,
                    p.image_url,
                    p.price as catalog_price,
                    p.cost_price as catalog_cost_price,
                    p.is_active,
                    p.site_id,
                    s.name as site_name,
                    COALESCE(sales.orders_count, 0) as orders_count,
                    COALESCE(sales.total_qty, 0) as total_qty,
                    COALESCE(sales.completed_qty, 0) as completed_qty,
                    COALESCE(sales.cancelled_qty, 0) as cancelled_qty,
                    COALESCE(sales.revenue, 0) as revenue,
                    COALESCE(sales.total_purchase, 0) as total_purchase,
                    COALESCE(sales.profit, 0) as profit,
                    CASE 
                        WHEN COALESCE(sales.revenue, 0) > 0 
                        THEN ROUND((COALESCE(sales.profit, 0) / sales.revenue) * 100, 1) 
                        ELSE 0 
                    END as margin_rate,
                    CASE 
                        WHEN (COALESCE(sales.completed_qty, 0) + COALESCE(sales.cancelled_qty, 0)) > 0 
                        THEN ROUND((COALESCE(sales.completed_qty, 0)::numeric / (sales.completed_qty + sales.cancelled_qty)) * 100, 1)
                        WHEN COALESCE(sales.total_qty, 0) > 0
                        THEN ROUND((COALESCE(sales.completed_qty, 0)::numeric / sales.total_qty) * 100, 1)
                        ELSE 0 
                    END as buyout_rate
                ');

            if ($siteId) {
                $productsQuery->where(function ($q) use ($siteId) {
                    $q->where('p.site_id', $siteId)
                        ->orWhereNull('p.site_id');
                });
            }

            if ($search && trim($search) !== '') {
                $escaped = StrHelper::escapeLike(trim($search));
                $productsQuery->where(function ($q) use ($escaped) {
                    $q->where('p.name', 'ilike', "%{$escaped}%")
                        ->orWhere('p.sku', 'ilike', "%{$escaped}%");
                });
            }

            if ($filter === 'with_sales') {
                $productsQuery->where(function ($q) {
                    $q->where('sales.total_qty', '>', 0)
                        ->orWhere('sales.completed_qty', '>', 0);
                });
            } elseif ($filter === 'without_sales') {
                $productsQuery->where('p.is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('sales.total_qty')
                            ->orWhere('sales.total_qty', '=', 0);
                    });
            } else {
                $productsQuery->where(function ($q) {
                    $q->where('p.is_active', true)
                        ->orWhere('sales.total_qty', '>', 0)
                        ->orWhere('sales.completed_qty', '>', 0);
                });
            }

            match ($sort) {
                'revenue_asc' => $productsQuery->orderBy('revenue', 'asc')->orderBy('p.name', 'asc'),
                'profit_desc' => $productsQuery->orderByDesc('profit')->orderByDesc('revenue'),
                'profit_asc' => $productsQuery->orderBy('profit', 'asc')->orderBy('p.name', 'asc'),
                'units_desc' => $productsQuery->orderByDesc('total_qty')->orderByDesc('revenue'),
                'orders_desc' => $productsQuery->orderByDesc('orders_count')->orderByDesc('revenue'),
                'margin_desc' => $productsQuery->orderByRaw('CASE WHEN COALESCE(sales.revenue, 0) > 0 THEN 0 ELSE 1 END ASC')
                    ->orderByDesc('margin_rate')
                    ->orderByDesc('revenue'),
                'buyout_desc' => $productsQuery->orderByRaw('CASE WHEN (COALESCE(sales.completed_qty, 0) + COALESCE(sales.cancelled_qty, 0)) > 0 OR COALESCE(sales.total_qty, 0) > 0 THEN 0 ELSE 1 END ASC')
                    ->orderByDesc('buyout_rate')
                    ->orderByDesc('total_qty'),
                'buyout_asc' => $productsQuery->orderByRaw('CASE WHEN (COALESCE(sales.completed_qty, 0) + COALESCE(sales.cancelled_qty, 0)) > 0 OR COALESCE(sales.total_qty, 0) > 0 THEN 0 ELSE 1 END ASC')
                    ->orderBy('buyout_rate', 'asc')
                    ->orderByDesc('total_qty'),
                'name_asc' => $productsQuery->orderBy('p.name', 'asc'),
                'name_desc' => $productsQuery->orderBy('p.name', 'desc'),
                default => $productsQuery->orderByDesc('revenue')->orderBy('p.name', 'asc'),
            };

            $total = (clone $productsQuery)->count();
            $offset = ($page - 1) * $perPage;
            $rows = $productsQuery->offset($offset)->limit($perPage)->get();
            $hasMore = ($offset + $rows->count()) < $total;
            $remaining = max(0, $total - ($offset + $rows->count()));

            $productsList = $rows->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'sku' => $row->sku,
                    'image_url' => $row->image_url,
                    'catalog_price' => (float) $row->catalog_price,
                    'catalog_cost_price' => (float) $row->catalog_cost_price,
                    'is_active' => (bool) $row->is_active,
                    'site_id' => $row->site_id ? (int) $row->site_id : null,
                    'site_name' => $row->site_name,
                    'orders_count' => (int) $row->orders_count,
                    'total_qty' => (int) $row->total_qty,
                    'completed_qty' => (int) $row->completed_qty,
                    'cancelled_qty' => (int) $row->cancelled_qty,
                    'revenue' => (float) $row->revenue,
                    'total_purchase' => (float) $row->total_purchase,
                    'profit' => (float) $row->profit,
                    'margin_rate' => (float) $row->margin_rate,
                    'buyout_rate' => (float) $row->buyout_rate,
                ];
            })->all();

            return [
                'period' => $period,
                'date_from' => $startDate->toIso8601String(),
                'date_to' => $endDate->toIso8601String(),
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_profit' => $totalProfit,
                    'total_units_sold' => (int) ($summaryRow->total_completed_qty ?? 0),
                    'total_units_ordered' => (int) ($summaryRow->total_qty ?? 0),
                    'unique_products_sold' => (int) ($summaryRow->unique_products_sold ?? 0),
                    'avg_margin_rate' => $avgMarginRate,
                ],
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_more' => $hasMore,
                    'remaining' => $remaining,
                ],
                'products' => $productsList,
            ];
        });
    }

    public function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 сек';
        }
        if ($seconds < 60) {
            return "{$seconds} сек";
        }
        $minutes = intdiv($seconds, 60);
        $remSeconds = $seconds % 60;
        if ($minutes < 60) {
            return $remSeconds > 0 ? "{$minutes} мин {$remSeconds} сек" : "{$minutes} мин";
        }
        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;
        if ($hours < 24) {
            return $remMinutes > 0 ? "{$hours} ч {$remMinutes} мин" : "{$hours} ч";
        }
        $days = intdiv($hours, 24);
        $remHours = $hours % 24;

        return $remHours > 0 ? "{$days} д {$remHours} ч" : "{$days} д";
    }

    public static function invalidateDashboardStatsCache(): void
    {
        try {
            Cache::increment('dashboard_stats_version');
        } catch (\Throwable) {
            Cache::put('dashboard_stats_version', time());
        }
    }

    public function getDashboardStats(string $period = 'today', ?string $from = null, ?string $to = null, ?string $date = null): object
    {
        $version = (int) Cache::get('dashboard_stats_version', 1);
        $cacheKey = "dashboard_stats_v{$version}_{$period}_{$from}_{$to}_{$date}";
        $ttl = ($period === 'today' && ! $date && ! $from) ? 60 : 3600;

        return Cache::remember($cacheKey, $ttl, function () use ($period, $from, $to, $date) {
            [$startDate, $endDate] = $this->resolveDateRange($period, $from, $to, $date);

            $ordersQuery = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', '!=', OrderStatus::CANCELLED)
                ->whereHas('site', fn ($q) => $q->active());

            $totals = (clone $ordersQuery)
                ->selectRaw('COALESCE(SUM(total_price), 0) as total_revenue, COALESCE(SUM(purchase_price), 0) as total_purchase')
                ->first();

            $liveRevenue = (float) ($totals->total_revenue ?? 0);
            $livePurchase = (float) ($totals->total_purchase ?? 0);
            $liveCourierPayouts = (float) Delivery::where(function ($dq) {
                $dq->where('deliveries.type', DeliveryType::COURIER)
                    ->orWhereNull('deliveries.type');
            })
                ->join('orders', 'orders.id', '=', 'deliveries.order_id')
                ->join('sites', 'sites.id', '=', 'orders.site_id')
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->where('orders.status', '!=', OrderStatus::CANCELLED)
                ->where('sites.is_active', true)
                ->sum('deliveries.delivery_cost');

            $earliestOrderCreatedAt = Cache::remember('stats_earliest_order_created_at', 3600, fn () => Order::min('created_at'));
            $histRevenue = 0.0;
            $histPurchase = 0.0;
            $histCourierPayouts = 0.0;
            $histNetProfit = 0.0;

            if ($earliestOrderCreatedAt === null) {
                $histStat = FinancialStat::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->whereHas('site', fn ($q) => $q->active())
                    ->selectRaw('
                        COALESCE(SUM(total_revenue), 0) as total_revenue,
                        COALESCE(SUM(total_purchase), 0) as total_purchase,
                        COALESCE(SUM(courier_payouts), 0) as courier_payouts,
                        COALESCE(SUM(net_profit), 0) as net_profit
                    ')
                    ->first();

                if ($histStat) {
                    $histRevenue = (float) $histStat->total_revenue;
                    $histPurchase = (float) $histStat->total_purchase;
                    $histCourierPayouts = (float) $histStat->courier_payouts;
                    $histNetProfit = (float) $histStat->net_profit;
                }
            } else {
                $earliestDate = Carbon::parse($earliestOrderCreatedAt)->startOfDay();
                if ($startDate->lt($earliestDate)) {
                    $histEnd = $earliestDate->copy()->subDay();
                    $effectiveEnd = $endDate->lt($histEnd) ? $endDate : $histEnd;
                    if ($startDate->lte($effectiveEnd)) {
                        $histStat = FinancialStat::whereBetween('date', [$startDate->toDateString(), $effectiveEnd->toDateString()])
                            ->whereHas('site', fn ($q) => $q->active())
                            ->selectRaw('
                                COALESCE(SUM(total_revenue), 0) as total_revenue,
                                COALESCE(SUM(total_purchase), 0) as total_purchase,
                                COALESCE(SUM(courier_payouts), 0) as courier_payouts,
                                COALESCE(SUM(net_profit), 0) as net_profit
                            ')
                            ->first();

                        if ($histStat) {
                            $histRevenue = (float) $histStat->total_revenue;
                            $histPurchase = (float) $histStat->total_purchase;
                            $histCourierPayouts = (float) $histStat->courier_payouts;
                            $histNetProfit = (float) $histStat->net_profit;
                        }
                    }
                }
            }

            $totalRevenue = $liveRevenue + $histRevenue;
            $totalPurchase = $livePurchase + $histPurchase;
            $courierPayouts = $liveCourierPayouts + $histCourierPayouts;
            $netProfit = ($liveRevenue - $livePurchase - $liveCourierPayouts) + $histNetProfit;

            return (object) [
                'total_revenue' => $totalRevenue,
                'total_purchase' => $totalPurchase,
                'courier_payouts' => $courierPayouts,
                'net_profit' => $netProfit,
            ];
        });
    }

    public function getFinancialHistory(string $metric = 'total_revenue', int $days = 30, ?string $fromStr = null, ?string $toStr = null): array
    {
        $allowedMetrics = ['total_revenue', 'total_purchase', 'courier_payouts', 'net_profit'];
        if (! in_array($metric, $allowedMetrics, true)) {
            $metric = 'total_revenue';
        }

        $version = (int) Cache::get('dashboard_stats_version', 1);
        $cacheKey = "financial_history_v{$version}_{$metric}_{$days}_{$fromStr}_{$toStr}";

        return Cache::remember($cacheKey, 300, function () use ($metric, $days, $fromStr, $toStr) {
            if ($fromStr && $toStr) {
                $from = Carbon::parse($fromStr)->startOfDay();
                $to = Carbon::parse($toStr)->startOfDay();

                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }
                if ($from->diffInDays($to) > 365) {
                    $from = $to->copy()->subDays(365);
                }

                $stats = FinancialStat::whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->whereHas('site', fn ($q) => $q->active())
                    ->groupBy('date')
                    ->orderBy('date')
                    ->selectRaw("date, SUM({$metric}) as total")
                    ->pluck('total', 'date');

                $labels = [];
                $values = [];
                $cursor = $from->copy();
                while ($cursor->lte($to)) {
                    $date = $cursor->toDateString();
                    $labels[] = $cursor->format('d.m');
                    $values[] = (float) ($stats[$date] ?? 0);
                    $cursor->addDay();
                }

                return [
                    'metric' => $metric,
                    'labels' => $labels,
                    'values' => $values,
                ];
            }

            $from = now()->subDays($days)->startOfDay();
            $to = now()->endOfDay();

            $stats = FinancialStat::whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->whereHas('site', fn ($q) => $q->active())
                ->groupBy('date')
                ->orderBy('date')
                ->selectRaw("date, SUM({$metric}) as total")
                ->pluck('total', 'date');

            $labels = [];
            $values = [];
            for ($i = $days; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $labels[] = now()->subDays($i)->format('d.m');
                $values[] = (float) ($stats[$date] ?? 0);
            }

            return [
                'metric' => $metric,
                'labels' => $labels,
                'values' => $values,
            ];
        });
    }

    public function getFinancialExportRows(Carbon $from, Carbon $to, ?int $siteId = null): array
    {
        $query = FinancialStat::with('site:id,domain,name')
            ->select(['id', 'site_id', 'date', 'total_revenue', 'total_purchase', 'courier_payouts', 'unforeseen_expenses', 'net_profit'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date', 'desc');

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $stats = $query->get();
        $coveredKeys = $stats->mapWithKeys(function ($stat) {
            return [$stat->date->format('Y-m-d').'_'.$stat->site_id => true];
        })->all();

        $rows = [];
        foreach ($stats as $stat) {
            $rows[] = [
                'date' => $stat->date->format('Y-m-d'),
                'site_name' => $stat->site?->domain ?? $stat->site?->name ?? '—',
                'revenue' => number_format((float) $stat->total_revenue, 2, '.', ''),
                'purchase' => number_format((float) $stat->total_purchase, 2, '.', ''),
                'courier' => number_format((float) $stat->courier_payouts, 2, '.', ''),
                'unforeseen' => number_format((float) $stat->unforeseen_expenses, 2, '.', ''),
                'net_profit' => number_format((float) $stat->net_profit, 2, '.', ''),
            ];
        }

        $ordersAggregates = Order::query()
            ->join('sites', 'sites.id', '=', 'orders.site_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value);

        if ($siteId) {
            $ordersAggregates->where('orders.site_id', $siteId);
        }

        $ordersAggregates = $ordersAggregates
            ->groupBy(DB::raw('DATE(orders.created_at)'), 'orders.site_id', 'sites.domain', 'sites.name')
            ->selectRaw('
                DATE(orders.created_at) as order_date,
                orders.site_id,
                COALESCE(sites.domain, sites.name, \'—\') as site_name,
                COALESCE(SUM(orders.total_price), 0) as total_revenue,
                COALESCE(SUM(orders.purchase_price), 0) as total_purchase
            ')
            ->get();

        $courierAggregates = Delivery::query()
            ->join('orders', 'orders.id', '=', 'deliveries.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->where(function ($dq) {
                $dq->where('deliveries.type', DeliveryType::COURIER->value)
                    ->orWhereNull('deliveries.type');
            });

        if ($siteId) {
            $courierAggregates->where('orders.site_id', $siteId);
        }

        $courierByDateSite = $courierAggregates
            ->groupBy(DB::raw('DATE(orders.created_at)'), 'orders.site_id')
            ->selectRaw('
                DATE(orders.created_at) as order_date,
                orders.site_id,
                COALESCE(SUM(deliveries.delivery_cost), 0) as courier_payouts
            ')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->order_date)->format('Y-m-d').'_'.$item->site_id);

        foreach ($ordersAggregates as $agg) {
            $dateStr = Carbon::parse($agg->order_date)->format('Y-m-d');
            $groupKey = $dateStr.'_'.$agg->site_id;
            if (isset($coveredKeys[$groupKey])) {
                continue;
            }

            $revenue = (float) $agg->total_revenue;
            $purchase = (float) $agg->total_purchase;
            $courier = (float) ($courierByDateSite->get($groupKey)?->courier_payouts ?? 0);
            $netProfit = $revenue - $purchase - $courier;

            $rows[] = [
                'date' => $dateStr,
                'site_name' => $agg->site_name,
                'revenue' => number_format($revenue, 2, '.', ''),
                'purchase' => number_format($purchase, 2, '.', ''),
                'courier' => number_format($courier, 2, '.', ''),
                'unforeseen' => '0.00',
                'net_profit' => number_format($netProfit, 2, '.', ''),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $rows;
    }
}
