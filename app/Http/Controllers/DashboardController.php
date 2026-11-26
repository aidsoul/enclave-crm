<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardStatsResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $stats = $this->analyticsService->getDashboardStats(
            $request->query('period', 'today'),
            $request->query('from'),
            $request->query('to'),
            $request->query('date')
        );

        return response()->json([
            'stats' => new DashboardStatsResource($stats),
        ]);
    }

    public function recentOrders(Request $request): JsonResponse
    {
        $orders = Order::with(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items.product', 'items.variant', 'discounts'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json(['orders' => OrderResource::collection($orders)]);
    }

    public function financialHistory(Request $request): JsonResponse
    {
        $metric = $request->query('metric', 'total_revenue');
        $days = min(365, max(1, (int) $request->query('days', 30)));
        $fromStr = $request->query('from');
        $toStr = $request->query('to');

        if ($fromStr && $toStr) {
            try {
                Carbon::parse($fromStr);
                Carbon::parse($toStr);
            } catch (\Throwable) {
                return response()->json([
                    'message' => 'Неверный формат даты для параметров from или to.',
                ], 422);
            }
        }

        $history = $this->analyticsService->getFinancialHistory($metric, $days, $fromStr, $toStr);

        return response()->json($history);
    }

    public function managersKpi(Request $request): JsonResponse
    {
        $period = $request->query('period', 'today');
        $from = $request->query('from');
        $to = $request->query('to');
        $date = $request->query('date');
        $managerId = $request->filled('manager_id') ? (int) $request->query('manager_id') : null;
        $search = $request->query('search');
        $filter = $request->query('filter');
        $sort = $request->query('sort');
        $page = $request->filled('page') ? (int) $request->query('page') : 1;
        $perPage = $request->filled('per_page') ? min(max((int) $request->query('per_page'), 1), 100) : null;

        $metrics = $this->analyticsService->getManagerMetrics(
            $period, $from, $to, $date, $managerId, $search, $filter, $sort, $page, $perPage
        );

        return response()->json($metrics);
    }

    public function couriersKpi(Request $request): JsonResponse
    {
        $period = $request->query('period', 'today');
        $from = $request->query('from');
        $to = $request->query('to');
        $date = $request->query('date');
        $courierId = $request->filled('courier_id') ? (int) $request->query('courier_id') : null;
        $search = $request->query('search');
        $filter = $request->query('filter');
        $sort = $request->query('sort');
        $page = $request->filled('page') ? (int) $request->query('page') : 1;
        $perPage = $request->filled('per_page') ? min(max((int) $request->query('per_page'), 1), 100) : null;

        $metrics = $this->analyticsService->getCourierMetrics(
            $period, $from, $to, $date, $courierId, $search, $filter, $sort, $page, $perPage
        );

        return response()->json($metrics);
    }

    public function courierShiftSummary(Request $request): JsonResponse
    {
        $user = auth()->user();
        $date = $request->query('date', now()->toDateString());
        $courierId = $user->isCourier()
            ? $user->id
            : ($request->filled('courier_id') ? (int) $request->query('courier_id') : null);

        $metrics = $this->analyticsService->getCourierMetrics('today', null, null, $date, $courierId);

        return response()->json($metrics);
    }

    public function productsKpi(Request $request): JsonResponse
    {
        $period = $request->query('period', 'today');
        $from = $request->query('from');
        $to = $request->query('to');
        $date = $request->query('date');
        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;
        $search = $request->query('search');
        $filter = $request->query('filter');
        $sort = $request->query('sort');
        $page = $request->filled('page') ? (int) $request->query('page') : 1;
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $metrics = $this->analyticsService->getProductMetrics(
            $period, $from, $to, $date, $siteId, $search, $filter, $sort, $page, $perPage
        );

        return response()->json($metrics);
    }
}
