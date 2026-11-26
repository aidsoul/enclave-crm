<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CustomerFilter
{
    /**
     * @return Builder<Customer>
     */
    public function apply(Request $request): Builder
    {
        $ltvSubquery = Order::selectRaw('COALESCE(SUM(total_price), 0)')
            ->whereColumn('customer_id', 'customers.id')
            ->where('status', '!=', OrderStatus::CANCELLED);

        $query = Customer::with('site')
            ->select('customers.*')
            ->selectSub($ltvSubquery, 'ltv')
            ->withCount([
                'orders',
                'orders as completed_orders_count' => function ($q) {
                    $q->where('status', OrderStatus::COMPLETED);
                },
            ]);

        if ($search = $request->query('search')) {
            $query->search($search);
        }

        if ($siteId = $request->query('site_id')) {
            $query->where('site_id', (int) $siteId);
        }

        if ($minOrders = $request->query('min_orders')) {
            $query->has('orders', '>=', (int) $minOrders);
        }

        if ($minLtv = $request->query('min_ltv')) {
            $query->where($ltvSubquery, '>=', (float) $minLtv);
        }

        if ($request->has('is_blacklisted')) {
            $query->where('is_blacklisted', $request->boolean('is_blacklisted'));
        }

        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'ltv_desc' => $query->orderByDesc('ltv'),
            'orders_desc' => $query->orderByDesc('orders_count'),
            'name_asc' => $query->orderBy('name'),
            default => $query->orderByDesc('id'),
        };

        return $query;
    }

    public function lookup(?string $phone, ?int $siteId = null): ?Customer
    {
        if (! $phone) {
            return null;
        }

        $normalized = PhoneNumber::normalize($phone);
        $cleanPhone = preg_replace('/\D/', '', $phone);

        $query = Customer::with('site')
            ->withCount('orders')
            ->withSum(['orders as ltv' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total_price');

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $query->where(function ($q) use ($normalized, $phone, $cleanPhone) {
            if ($normalized) {
                $q->where('phone', $normalized)
                    ->orWhere('phone', '+'.$normalized);
            }
            if ($cleanPhone && $cleanPhone !== $normalized) {
                $q->orWhere('phone', $cleanPhone)
                    ->orWhere('phone', '+'.$cleanPhone);
            }
            if ($phone && $phone !== $normalized && $phone !== $cleanPhone) {
                $q->orWhere('phone', $phone);
            }
        });

        return $query->first();
    }
}
