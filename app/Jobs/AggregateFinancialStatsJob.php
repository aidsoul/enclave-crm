<?php

namespace App\Jobs;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\FinancialStat;
use App\Models\Order;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateFinancialStatsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function __construct(
        public ?string $date = null
    ) {
        $this->onConnection('redis-low')->onQueue('low');
    }

    public function uniqueId(): string
    {
        try {
            return $this->date ? Carbon::parse($this->date)->toDateString() : now()->subDay()->toDateString();
        } catch (\Throwable) {
            return 'invalid-date';
        }
    }

    public function handle(): void
    {
        try {
            $date = $this->date ? Carbon::parse($this->date) : now()->subDay();
        } catch (InvalidFormatException) {
            return;
        }

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $dateString = $date->toDateString();

        $stats = Order::whereBetween('orders.created_at', [$start, $end])
            ->where('orders.status', '!=', OrderStatus::CANCELLED)
            ->join('sites', 'sites.id', '=', 'orders.site_id')
            ->where('sites.is_active', true)
            ->groupBy('orders.site_id')
            ->selectRaw('
                orders.site_id,
                COALESCE(SUM(orders.total_price), 0) as total_revenue,
                COALESCE(SUM(orders.purchase_price), 0) as total_purchase
            ')
            ->get();

        $statsBySite = $stats->keyBy('site_id');

        $courierPayouts = Delivery::whereBetween('orders.created_at', [$start, $end])
            ->where('orders.status', '!=', OrderStatus::CANCELLED)
            ->where(function ($dq) {
                $dq->where('deliveries.type', DeliveryType::COURIER)
                    ->orWhereNull('deliveries.type');
            })
            ->join('orders', 'orders.id', '=', 'deliveries.order_id')
            ->join('sites', 'sites.id', '=', 'orders.site_id')
            ->where('sites.is_active', true)
            ->groupBy('orders.site_id')
            ->selectRaw('orders.site_id, COALESCE(SUM(deliveries.delivery_cost), 0) as courier_payouts')
            ->pluck('courier_payouts', 'site_id');

        $existingStats = FinancialStat::where('date', $dateString)->get()->keyBy('site_id');

        $allSiteIds = $statsBySite->keys()
            ->merge($courierPayouts->keys())
            ->merge($existingStats->keys())
            ->unique();

        $upsertData = [];
        $now = now();
        foreach ($allSiteIds as $siteId) {
            $siteId = (int) $siteId;
            $row = $statsBySite->get($siteId);
            $totalRevenue = (float) ($row?->total_revenue ?? 0);
            $totalPurchase = (float) ($row?->total_purchase ?? 0);
            $payout = (float) ($courierPayouts[$siteId] ?? 0);

            $existing = $existingStats->get($siteId);
            $unforeseen = (float) ($existing?->unforeseen_expenses ?? 0);
            $netProfit = $totalRevenue - $totalPurchase - $payout - $unforeseen;

            $upsertData[] = [
                'site_id' => $siteId,
                'date' => $dateString,
                'total_revenue' => $totalRevenue,
                'total_purchase' => $totalPurchase,
                'courier_payouts' => $payout,
                'unforeseen_expenses' => $unforeseen,
                'net_profit' => $netProfit,
                'created_at' => $existing?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($upsertData)) {
            FinancialStat::upsert(
                $upsertData,
                ['site_id', 'date'],
                ['total_revenue', 'total_purchase', 'courier_payouts', 'unforeseen_expenses', 'net_profit', 'updated_at']
            );
        }
    }
}
