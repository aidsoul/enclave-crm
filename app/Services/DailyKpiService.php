<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\DailyUserKpi;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyKpiService
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    public function aggregateDay(Carbon|string $targetDate): int
    {
        $date = is_string($targetDate) ? Carbon::parse($targetDate) : $targetDate->copy();
        $startDate = $date->copy()->startOfDay();
        $endDate = $date->copy()->endOfDay();
        $dateString = $date->toDateString();

        $activeManagerIds = User::whereIn('role', [UserRole::MANAGER, UserRole::OWNER])
            ->where('is_active', true)
            ->pluck('id');

        $orderManagerIds = Order::whereNotNull('manager_id')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct()
            ->pluck('manager_id');

        $managerIds = $activeManagerIds->merge($orderManagerIds)->unique();
        $managers = User::whereIn('id', $managerIds)->get();

        $managerStats = $this->analyticsService->calculateManagersRaw($startDate, $endDate, $managers);

        $now = now();
        $records = [];

        foreach ($managers as $manager) {
            $stat = $managerStats[$manager->id] ?? [];

            $records[$manager->id] = [
                'user_id' => $manager->id,
                'date' => $dateString,
                'role' => $manager->role->value,
                'accepted_orders' => (int) ($stat['accepted_orders'] ?? 0),
                'completed_orders' => (int) ($stat['completed_orders'] ?? 0),
                'cancelled_orders' => (int) ($stat['cancelled_orders'] ?? 0),
                'in_progress_orders' => (int) ($stat['in_progress_orders'] ?? 0),
                'total_response_time_seconds' => (int) ($stat['total_response_time_seconds'] ?? 0),
                'response_orders_count' => (int) ($stat['response_orders_count'] ?? 0),
                'completed_deliveries' => 0,
                'total_collected' => 0.0,
                'cash_amount' => 0.0,
                'terminal_amount' => 0.0,
                'other_amount' => 0.0,
                'payout_amount' => 0.0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $activeCourierIds = User::where('role', UserRole::COURIER)
            ->where('is_active', true)
            ->pluck('id');

        $deliveryCourierIds = Delivery::whereNotNull('user_id')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('delivery_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('created_at', [$startDate, $endDate])
                    ->orWhereBetween('updated_at', [$startDate, $endDate]);
            })
            ->distinct()
            ->pluck('user_id');

        $courierIds = $activeCourierIds->merge($deliveryCourierIds)->unique();
        $couriers = User::whereIn('id', $courierIds)->get();

        $courierStats = $this->analyticsService->calculateCouriersRaw($startDate, $endDate, $couriers);

        foreach ($couriers as $courier) {
            $stat = $courierStats[$courier->id] ?? [];

            if (isset($records[$courier->id])) {
                $records[$courier->id]['completed_deliveries'] = (int) ($stat['completed_deliveries'] ?? 0);
                $records[$courier->id]['total_collected'] = (float) ($stat['total_collected'] ?? 0);
                $records[$courier->id]['cash_amount'] = (float) ($stat['cash_amount'] ?? 0);
                $records[$courier->id]['terminal_amount'] = (float) ($stat['terminal_amount'] ?? 0);
                $records[$courier->id]['other_amount'] = (float) ($stat['other_amount'] ?? 0);
                $records[$courier->id]['payout_amount'] = (float) ($stat['payout_amount'] ?? 0);
            } else {
                $records[$courier->id] = [
                    'user_id' => $courier->id,
                    'date' => $dateString,
                    'role' => $courier->role->value,
                    'accepted_orders' => 0,
                    'completed_orders' => 0,
                    'cancelled_orders' => 0,
                    'in_progress_orders' => 0,
                    'total_response_time_seconds' => 0,
                    'response_orders_count' => 0,
                    'completed_deliveries' => (int) ($stat['completed_deliveries'] ?? 0),
                    'total_collected' => (float) ($stat['total_collected'] ?? 0),
                    'cash_amount' => (float) ($stat['cash_amount'] ?? 0),
                    'terminal_amount' => (float) ($stat['terminal_amount'] ?? 0),
                    'other_amount' => (float) ($stat['other_amount'] ?? 0),
                    'payout_amount' => (float) ($stat['payout_amount'] ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $allRecords = array_values($records);

        if (! empty($allRecords)) {
            DB::transaction(function () use ($allRecords) {
                DailyUserKpi::upsert(
                    $allRecords,
                    ['user_id', 'date'],
                    [
                        'role',
                        'accepted_orders',
                        'completed_orders',
                        'cancelled_orders',
                        'in_progress_orders',
                        'total_response_time_seconds',
                        'response_orders_count',
                        'completed_deliveries',
                        'total_collected',
                        'cash_amount',
                        'terminal_amount',
                        'other_amount',
                        'payout_amount',
                        'updated_at',
                    ]
                );
            });
        }

        return count($allRecords);
    }

    public function backfill(Carbon|string $from, Carbon|string $to): int
    {
        $startDate = is_string($from) ? Carbon::parse($from) : $from->copy();
        $endDate = is_string($to) ? Carbon::parse($to) : $to->copy();

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $current = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        $daysProcessed = 0;
        while ($current->lte($end)) {
            $this->aggregateDay($current);
            $daysProcessed++;
            $current->addDay();
        }

        return $daysProcessed;
    }
}
