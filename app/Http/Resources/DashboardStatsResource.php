<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read float $total_revenue
 * @property-read float $total_purchase
 * @property-read float $courier_payouts
 * @property-read float $net_profit
 */
class DashboardStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_revenue' => round($this->total_revenue, 2),
            'total_purchase' => round($this->total_purchase, 2),
            'courier_payouts' => round($this->courier_payouts, 2),
            'net_profit' => round($this->net_profit, 2),
        ];
    }
}
