<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Support\StrHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderFilter
{
    /**
     * @return Builder<Order>
     */
    public function apply(Request $request, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        $relations = ['site', 'manager', 'customer', 'delivery.courier', 'payment'];
        if ($request->boolean('with_items') || $request->has('include_items')) {
            $relations[] = 'items.product';
            $relations[] = 'items.variant';
        }

        $query = Order::with($relations);

        if ($user && $user->role === UserRole::COURIER) {
            $query->whereHas('delivery', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->has('date')) {
            $date = $request->input('date');
            if ($date === 'today') {
                $query->createdToday();
            } elseif ($date === 'yesterday') {
                $query->whereDate('created_at', now()->subDay()->toDateString());
            } elseif ($date === 'week') {
                $query->where('created_at', '>=', now()->subDays(7)->startOfDay());
            } elseif ($date === 'month') {
                $query->where('created_at', '>=', now()->subDays(30)->startOfDay());
            }
        }

        if ($request->filled('date_from')) {
            try {
                $query->where('created_at', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
            } catch (\Throwable) {
                // Ignore invalid date format
            }
        }

        if ($request->filled('date_to')) {
            try {
                $query->where('created_at', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
            } catch (\Throwable) {
                // Ignore invalid date format
            }
        }

        if ($request->filled('site_id')) {
            $query->forSite((int) $request->input('site_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }

        if ($request->filled('status')) {
            $status = OrderStatus::tryFrom((string) $request->input('status'));
            if ($status) {
                $query->byStatus($status);
            }
        }

        if ($request->filled('manager_id')) {
            $managerId = $request->input('manager_id');
            if ($managerId === 'unassigned' || $managerId === 'none') {
                $query->whereNull('manager_id');
            } else {
                $query->forManager((int) $managerId);
            }
        } elseif ($request->has('with_manager')) {
            $query->whereNotNull('manager_id');
        }

        if ($request->filled('courier_id')) {
            $courierId = $request->input('courier_id');
            if ($courierId === 'unassigned' || $courierId === 'none') {
                $query->whereNull('courier_id')->whereDoesntHave('delivery', fn ($q) => $q->whereNotNull('user_id'));
            } else {
                $query->forCourier((int) $courierId);
            }
        }

        if ($request->filled('min_price')) {
            $query->where('total_price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('total_price', '<=', (float) $request->input('max_price'));
        }

        if ($request->has('external_id')) {
            $query->where('external_id', (string) $request->input('external_id'));
        }

        if ($search = $request->query('search')) {
            $escapedSearch = StrHelper::escapeLike($search);
            $query->where(function ($q) use ($search, $escapedSearch) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('external_id', 'ilike', "%{$escapedSearch}%");

                $q->orWhereHas('customer', function ($q2) use ($escapedSearch) {
                    $q2->where('name', 'ilike', "%{$escapedSearch}%")
                        ->orWhere('phone', 'ilike', "%{$escapedSearch}%")
                        ->orWhere('email', 'ilike', "%{$escapedSearch}%");
                });
            });
        }

        $allowedSortBy = ['created_at', 'total_price', 'updated_at', 'id', 'delivery_date', 'delivery_slot'];
        $sortBy = in_array($request->query('sort_by'), $allowedSortBy, true) ? $request->query('sort_by') : 'created_at';
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['delivery_date', 'delivery_slot'], true)) {
            $query->orderByRaw('(SELECT d.delivery_date FROM deliveries d WHERE d.order_id = orders.id ORDER BY d.id DESC LIMIT 1) '.$sortOrder.' NULLS LAST')
                ->orderByRaw('(SELECT d.delivery_slot FROM deliveries d WHERE d.order_id = orders.id ORDER BY d.id DESC LIMIT 1) '.$sortOrder.' NULLS LAST');
        } else {
            $query->orderBy('orders.'.$sortBy, $sortOrder);
        }

        if ($sortBy !== 'id') {
            $query->orderBy('orders.id', 'desc');
        }

        return $query;
    }
}
