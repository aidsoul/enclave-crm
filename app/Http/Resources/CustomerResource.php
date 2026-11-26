<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 *
 * @property-read string|null $phone
 * @property-read string|null $formatted_phone
 * @property-read string|null $phone_with_plus
 * @property-read float|null $ltv
 * @property-read int|null $orders_count
 * @property-read int|null $completed_orders_count
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ltv = isset($this->ltv)
            ? (float) $this->ltv
            : $this->calculateLtv();

        $ordersCount = $this->orders_count ?? $this->orders()->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'formatted_phone' => $this->formatted_phone,
            'phone_with_plus' => $this->phone_with_plus,
            'email' => $this->email,
            'address' => $this->address,
            'is_blacklisted' => (bool) $this->is_blacklisted,
            'blacklist_reason' => $this->blacklist_reason,
            'tags' => $this->tags ?? [],
            'site_id' => $this->site_id,
            'site' => $this->site ? [
                'id' => $this->site->id,
                'name' => $this->site->name,
                'domain' => $this->site->domain,
                'display_name' => $this->site->display_name,
            ] : null,
            'orders_count' => $ordersCount,
            'ltv' => round($ltv, 2),
            'created_at' => $this->created_at,
            'orders' => $this->whenLoaded('orders', function () {
                return $this->orders->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'status' => $order->status->value,
                        'status_label' => $order->status->label(),
                        'status_color' => $order->status->color(),
                        'total_price' => (float) $order->total_price,
                        'created_at' => $order->created_at,
                        'site' => $order->site ? [
                            'id' => $order->site->id,
                            'domain' => $order->site->domain,
                            'display_name' => $order->site->display_name,
                        ] : null,
                        'items' => $order->relationLoaded('items') ? $order->items->map(fn ($item) => [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_variant_id' => $item->product_variant_id,
                            'name' => $item->name,
                            'qty' => $item->qty,
                            'price' => (float) $item->price,
                        ]) : [],
                    ];
                });
            }),
        ];
    }
}
