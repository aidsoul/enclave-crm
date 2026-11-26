<?php

namespace Tests\Feature\Analytics;

use App\Enums\ActionType;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Jobs\AggregateDailyUserKpisJob;
use App\Models\ActionLog;
use App\Models\Customer;
use App\Models\DailyUserKpi;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\DailyKpiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyUserKpiHybridTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected User $courier;

    protected Site $site;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 10001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Alex',
            'telegram_user_id' => 10002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Dmitry',
            'telegram_user_id' => 10003,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Store 1',
            'domain' => 'store1.example.com',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Customer Test',
            'phone' => '+79991112233',
        ]);
    }

    public function test_daily_kpi_aggregation_command_and_job(): void
    {
        $yesterday = Carbon::yesterday();

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 2000,
            'purchase_price' => 1000,
        ]);
        $order->created_at = $yesterday->copy()->setHour(12);
        $order->save();

        ActionLog::create([
            'actor_id' => $this->manager->id,
            'order_id' => $order->id,
            'action_type' => ActionType::ASSIGN,
            'field_name' => 'manager_id',
            'old_value' => '',
            'new_value' => (string) $this->manager->id,
            'created_at' => $yesterday->copy()->setHour(12)->addMinutes(4),
        ]);

        $order->delivery()->create([
            'type' => DeliveryType::COURIER,
            'user_id' => $this->courier->id,
            'delivery_cost' => 350,
            'delivery_date' => $yesterday->toDateString(),
        ]);

        $order->payment()->create([
            'method' => PaymentMethod::CASH,
            'amount' => 2000,
            'status' => PaymentStatus::PAID,
        ]);

        $this->artisan('kpi:aggregate-daily', ['--date' => $yesterday->toDateString()])
            ->assertSuccessful();

        $managerKpi = DailyUserKpi::where('user_id', $this->manager->id)
            ->whereDate('date', $yesterday)
            ->first();

        $this->assertNotNull($managerKpi);
        $this->assertEquals(1, $managerKpi->accepted_orders);
        $this->assertEquals(1, $managerKpi->completed_orders);
        $this->assertEquals(240, $managerKpi->total_response_time_seconds);
        $this->assertEquals(1, $managerKpi->response_orders_count);

        $courierKpi = DailyUserKpi::where('user_id', $this->courier->id)
            ->whereDate('date', $yesterday)
            ->first();

        $this->assertNotNull($courierKpi);
        $this->assertEquals(1, $courierKpi->completed_deliveries);
        $this->assertEquals(2000.00, (float) $courierKpi->total_collected);
        $this->assertEquals(2000.00, (float) $courierKpi->cash_amount);
        $this->assertEquals(350.00, (float) $courierKpi->payout_amount);

        // Test AggregateDailyUserKpisJob dispatch
        dispatch_sync(new AggregateDailyUserKpisJob($yesterday->toDateString()));
        $this->assertDatabaseHas('daily_user_kpis', [
            'user_id' => $this->manager->id,
            'date' => $yesterday->toDateString(),
        ]);
    }

    public function test_hybrid_kpi_calculation_combines_history_and_today(): void
    {
        $yesterday = Carbon::yesterday();
        $today = Carbon::today();

        // Historical day (yesterday)
        DailyUserKpi::create([
            'user_id' => $this->manager->id,
            'date' => $yesterday->toDateString(),
            'role' => UserRole::MANAGER,
            'accepted_orders' => 2,
            'completed_orders' => 1,
            'cancelled_orders' => 1,
            'in_progress_orders' => 0,
            'total_response_time_seconds' => 120, // 60s avg
            'response_orders_count' => 2,
        ]);

        DailyUserKpi::create([
            'user_id' => $this->courier->id,
            'date' => $yesterday->toDateString(),
            'role' => UserRole::COURIER,
            'completed_deliveries' => 3,
            'total_collected' => 5000,
            'cash_amount' => 3000,
            'terminal_amount' => 2000,
            'other_amount' => 0,
            'payout_amount' => 900,
        ]);

        // Today's live activity
        $todayOrder = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 1500,
            'purchase_price' => 800,
        ]);
        $todayOrder->created_at = $today->copy()->setHour(10);
        $todayOrder->save();

        ActionLog::create([
            'actor_id' => $this->manager->id,
            'order_id' => $todayOrder->id,
            'action_type' => ActionType::ASSIGN,
            'field_name' => 'manager_id',
            'old_value' => '',
            'new_value' => (string) $this->manager->id,
            'created_at' => $today->copy()->setHour(10)->addMinutes(1), // 60s
        ]);

        $todayOrder->delivery()->create([
            'type' => DeliveryType::COURIER,
            'user_id' => $this->courier->id,
            'delivery_cost' => 300,
            'delivery_date' => $today->toDateString(),
        ]);

        $todayOrder->payment()->create([
            'method' => PaymentMethod::TERMINAL,
            'amount' => 1500,
            'status' => PaymentStatus::PAID,
        ]);

        /** @var AnalyticsService $analytics */
        $analytics = app(AnalyticsService::class);

        // Query custom range covering yesterday and today
        $managerRes = $analytics->getManagerMetrics(
            period: 'custom',
            from: $yesterday->toDateString(),
            to: $today->toDateString()
        );

        $mStat = collect($managerRes['managers'])->firstWhere('id', $this->manager->id);
        $this->assertNotNull($mStat);
        $this->assertEquals(3, $mStat['accepted_orders']); // 2 hist + 1 today
        $this->assertEquals(2, $mStat['completed_orders']); // 1 hist + 1 today
        $this->assertEquals(1, $mStat['cancelled_orders']); // 1 hist + 0 today
        $this->assertEquals(66.7, $mStat['conversion_rate']); // 2/3 = 66.7%
        // Response time: (120s + 60s) / 3 orders = 60s
        $this->assertEquals(60, $mStat['avg_response_time_seconds']);

        $courierRes = $analytics->getCourierMetrics(
            period: 'custom',
            from: $yesterday->toDateString(),
            to: $today->toDateString()
        );

        $cStat = collect($courierRes['couriers'])->firstWhere('id', $this->courier->id);
        $this->assertNotNull($cStat);
        $this->assertEquals(4, $cStat['completed_deliveries']); // 3 hist + 1 today
        $this->assertEquals(6500.00, $cStat['total_collected']); // 5000 + 1500
        $this->assertEquals(3000.00, $cStat['cash_amount']); // 3000 + 0
        $this->assertEquals(3500.00, $cStat['terminal_amount']); // 2000 + 1500
        $this->assertEquals(1200.00, $cStat['payout_amount']); // 900 + 300
    }

    public function test_backfill_artisan_command(): void
    {
        $d1 = Carbon::today()->subDays(3);
        $d2 = Carbon::today()->subDays(2);

        $this->artisan('kpi:backfill', [
            '--from' => $d1->toDateString(),
            '--to' => $d2->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseHas('daily_user_kpis', [
            'user_id' => $this->manager->id,
            'date' => $d1->toDateString(),
        ]);
        $this->assertDatabaseHas('daily_user_kpis', [
            'user_id' => $this->manager->id,
            'date' => $d2->toDateString(),
        ]);
    }

    public function test_historical_gap_falls_back_to_raw_calculation(): void
    {
        $d1 = Carbon::today()->subDays(2);
        $d2 = Carbon::today()->subDays(1);

        // Day 1 has raw order
        $order1 = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 1000,
            'purchase_price' => 500,
        ]);
        $order1->created_at = $d1->copy()->setHour(12);
        $order1->save();

        // Day 2 was aggregated into DailyUserKpi, but Day 1 was NOT aggregated
        DailyUserKpi::create([
            'user_id' => $this->manager->id,
            'date' => $d2->toDateString(),
            'role' => UserRole::MANAGER,
            'accepted_orders' => 1,
            'completed_orders' => 1,
            'cancelled_orders' => 0,
            'in_progress_orders' => 0,
            'total_response_time_seconds' => 30,
            'response_orders_count' => 1,
        ]);

        // Day 2 also has a matching order in DB
        $order2 = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 1000,
            'purchase_price' => 500,
        ]);
        $order2->created_at = $d2->copy()->setHour(12);
        $order2->save();

        /** @var AnalyticsService $analytics */
        $analytics = app(AnalyticsService::class);

        // Query 2-day period covering both d1 and d2
        $res = $analytics->getManagerMetrics(
            period: 'custom',
            from: $d1->toDateString(),
            to: $d2->toDateString()
        );

        $stat = collect($res['managers'])->firstWhere('id', $this->manager->id);
        $this->assertNotNull($stat);
        // Because of the gap (day 1 missing from DailyUserKpi), it falls back to raw calculation
        // and does NOT drop day 1! Both orders are counted:
        $this->assertEquals(2, $stat['accepted_orders']);
        $this->assertEquals(2, $stat['completed_orders']);
    }

    public function test_deactivated_user_with_activity_is_aggregated(): void
    {
        $yesterday = Carbon::yesterday();

        $inactiveManager = User::create([
            'system_name' => 'Former Manager',
            'telegram_user_id' => 99999,
            'role' => UserRole::MANAGER,
            'is_active' => false,
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $inactiveManager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 500,
            'purchase_price' => 250,
        ]);
        $order->created_at = $yesterday->copy()->setHour(10);
        $order->save();

        /** @var DailyKpiService $service */
        $service = app(DailyKpiService::class);
        $processed = $service->aggregateDay($yesterday);

        $this->assertGreaterThanOrEqual(1, $processed);
        $this->assertDatabaseHas('daily_user_kpis', [
            'user_id' => $inactiveManager->id,
            'date' => $yesterday->toDateString(),
            'accepted_orders' => 1,
            'completed_orders' => 1,
        ]);
    }
}
