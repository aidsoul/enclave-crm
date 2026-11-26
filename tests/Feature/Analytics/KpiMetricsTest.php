<?php

namespace Tests\Feature\Analytics;

use App\Enums\ActionType;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\ActionLog;
use App\Models\Customer;
use App\Models\FinancialStat;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiMetricsTest extends TestCase
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

    public function test_manager_response_speed_and_conversion_calculation(): void
    {
        $createdAt = Carbon::now()->subMinutes(20);

        // Order 1: Created 20 mins ago, accepted 15 mins ago (5 mins response time), completed (delivered)
        $order1 = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 1500,
            'purchase_price' => 800,
        ]);
        $order1->created_at = $createdAt;
        $order1->save();

        ActionLog::create([
            'actor_id' => $this->manager->id,
            'order_id' => $order1->id,
            'action_type' => ActionType::ASSIGN,
            'field_name' => 'manager_id',
            'old_value' => '',
            'new_value' => (string) $this->manager->id,
            'created_at' => $createdAt->copy()->addMinutes(5),
        ]);

        // Order 2: Created 10 mins ago, accepted 7 mins ago (3 mins response time), cancelled
        $order2 = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::CANCELLED,
            'total_price' => 2000,
            'purchase_price' => 1000,
        ]);
        $order2->created_at = Carbon::now()->subMinutes(10);
        $order2->save();

        ActionLog::create([
            'actor_id' => $this->manager->id,
            'order_id' => $order2->id,
            'action_type' => ActionType::STATUS_CHANGE,
            'field_name' => 'status',
            'old_value' => OrderStatus::CREATED->value,
            'new_value' => OrderStatus::ACCEPTED->value,
            'created_at' => Carbon::now()->subMinutes(7),
        ]);

        $service = app(AnalyticsService::class);
        $metrics = $service->getManagerMetrics('today');

        $this->assertEquals(2, $metrics['summary']['total_accepted']);
        $this->assertEquals(1, $metrics['summary']['total_completed']);
        $this->assertEquals(1, $metrics['summary']['total_cancelled']);
        $this->assertEquals(50.0, $metrics['summary']['conversion_rate']);

        // Expected average response time: (300 + 180) / 2 = 240 seconds (4 mins)
        $this->assertEquals(240, $metrics['summary']['avg_response_time_seconds']);
        $this->assertStringContainsString('4 мин', $metrics['summary']['avg_response_time_formatted']);

        $mStats = collect($metrics['managers'])->firstWhere('id', $this->manager->id);
        $this->assertNotNull($mStats);
        $this->assertEquals(2, $mStats['accepted_orders']);
        $this->assertEquals(1, $mStats['completed_orders']);
        $this->assertEquals(50.0, $mStats['conversion_rate']);
        $this->assertEquals(240, $mStats['avg_response_time_seconds']);
    }

    public function test_courier_deliveries_and_collected_payments_calculation(): void
    {
        // Order delivered today with CASH payment
        $order1 = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 3000,
            'purchase_price' => 1500,
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        $order1->delivery()->create([
            'type' => DeliveryType::COURIER,
            'user_id' => $this->courier->id,
            'delivery_cost' => 350,
            'delivery_date' => Carbon::today(),
        ]);

        $order1->payment()->create([
            'method' => PaymentMethod::CASH,
            'status' => PaymentStatus::PAID,
            'amount' => 3000,
        ]);

        ActionLog::create([
            'actor_id' => $this->courier->id,
            'order_id' => $order1->id,
            'action_type' => ActionType::STATUS_CHANGE,
            'field_name' => 'status',
            'old_value' => OrderStatus::AT_WAREHOUSE->value,
            'new_value' => OrderStatus::COMPLETED->value,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        // Order delivered today with TERMINAL payment
        $order2 = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 2500,
            'purchase_price' => 1200,
            'created_at' => Carbon::now()->subHours(1),
            'updated_at' => Carbon::now()->subMinutes(10),
        ]);

        $order2->delivery()->create([
            'type' => DeliveryType::COURIER,
            'user_id' => $this->courier->id,
            'delivery_cost' => 300,
            'delivery_date' => Carbon::today(),
        ]);

        $order2->payment()->create([
            'method' => PaymentMethod::TERMINAL,
            'status' => PaymentStatus::PAID,
            'amount' => 2500,
        ]);

        ActionLog::create([
            'actor_id' => $this->courier->id,
            'order_id' => $order2->id,
            'action_type' => ActionType::STATUS_CHANGE,
            'field_name' => 'status',
            'old_value' => OrderStatus::AT_WAREHOUSE->value,
            'new_value' => OrderStatus::COMPLETED->value,
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        $service = app(AnalyticsService::class);
        $metrics = $service->getCourierMetrics('today');

        $this->assertEquals(2, $metrics['summary']['total_deliveries']);
        $this->assertEquals(5500.0, $metrics['summary']['total_collected']);
        $this->assertEquals(3000.0, $metrics['summary']['cash_amount']);
        $this->assertEquals(2500.0, $metrics['summary']['terminal_amount']);
        $this->assertEquals(650.0, $metrics['summary']['payout_amount']);

        $cStats = collect($metrics['couriers'])->firstWhere('id', $this->courier->id);
        $this->assertNotNull($cStats);
        $this->assertEquals(2, $cStats['completed_deliveries']);
        $this->assertEquals(5500.0, $cStats['total_collected']);
        $this->assertEquals(3000.0, $cStats['cash_amount']);
        $this->assertEquals(2500.0, $cStats['terminal_amount']);
        $this->assertEquals(650.0, $cStats['payout_amount']);
    }

    public function test_api_dashboard_managers_kpi_endpoint_requires_owner(): void
    {
        $this->actingAs($this->courier)
            ->getJson('/api/dashboard/managers-kpi')
            ->assertStatus(403);

        $this->actingAs($this->owner)
            ->getJson('/api/dashboard/managers-kpi')
            ->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'summary' => [
                    'total_accepted',
                    'total_completed',
                    'total_cancelled',
                    'conversion_rate',
                    'avg_response_time_seconds',
                    'avg_response_time_formatted',
                ],
                'managers',
            ]);
    }

    public function test_api_dashboard_couriers_kpi_endpoint_requires_owner(): void
    {
        $this->actingAs($this->manager)
            ->getJson('/api/dashboard/couriers-kpi')
            ->assertStatus(403);

        $this->actingAs($this->owner)
            ->getJson('/api/dashboard/couriers-kpi')
            ->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'summary' => [
                    'total_deliveries',
                    'total_collected',
                    'cash_amount',
                    'terminal_amount',
                    'payout_amount',
                ],
                'couriers',
            ]);
    }

    public function test_courier_shift_summary_endpoint(): void
    {
        // Order delivered today by courier
        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 1800,
            'purchase_price' => 900,
            'created_at' => Carbon::now()->subHours(1),
            'updated_at' => Carbon::now()->subMinutes(15),
        ]);

        $order->delivery()->create([
            'type' => DeliveryType::COURIER,
            'user_id' => $this->courier->id,
            'delivery_cost' => 250,
            'delivery_date' => Carbon::today(),
        ]);

        $order->payment()->create([
            'method' => PaymentMethod::CASH,
            'status' => PaymentStatus::PAID,
            'amount' => 1800,
        ]);

        ActionLog::create([
            'actor_id' => $this->courier->id,
            'order_id' => $order->id,
            'action_type' => ActionType::STATUS_CHANGE,
            'field_name' => 'status',
            'old_value' => OrderStatus::AT_WAREHOUSE->value,
            'new_value' => OrderStatus::COMPLETED->value,
            'created_at' => Carbon::now()->subMinutes(15),
        ]);

        $response = $this->actingAs($this->courier)
            ->getJson('/api/courier/shift-summary')
            ->assertStatus(200)
            ->assertJsonStructure([
                'summary' => [
                    'total_deliveries',
                    'total_collected',
                    'cash_amount',
                    'terminal_amount',
                    'payout_amount',
                ],
                'couriers',
            ]);

        $data = $response->json();
        $this->assertEquals(1, $data['summary']['total_deliveries']);
        $this->assertEquals(1800.0, $data['summary']['total_collected']);
        $this->assertEquals(1800.0, $data['summary']['cash_amount']);
        $this->assertEquals(250.0, $data['summary']['payout_amount']);
    }

    public function test_format_duration_helper(): void
    {
        $service = app(AnalyticsService::class);

        $this->assertEquals('0 сек', $service->formatDuration(0));
        $this->assertEquals('45 сек', $service->formatDuration(45));
        $this->assertEquals('5 мин', $service->formatDuration(300));
        $this->assertEquals('4 мин 15 сек', $service->formatDuration(255));
        $this->assertEquals('1 ч', $service->formatDuration(3600));
        $this->assertEquals('1 ч 30 мин', $service->formatDuration(5400));
        $this->assertEquals('1 д 2 ч', $service->formatDuration(93600));
    }

    public function test_tma_analytics_page_access(): void
    {
        $this->actingAs($this->courier)
            ->get('/tma/analytics')
            ->assertSee('Доступ ограничен');

        $this->actingAs($this->owner)
            ->get('/tma/analytics')
            ->assertStatus(200)
            ->assertSee('Аналитика и KPI');
    }

    public function test_server_side_managers_kpi_search_filter_sort_and_pagination(): void
    {
        // Manager Alex has orders (from setUp or new), create second manager
        $manager2 = User::create([
            'system_name' => 'Manager Boris',
            'telegram_user_id' => 10009,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 1000,
            'purchase_price' => 500,
        ]);

        // Search by name
        $res = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/managers-kpi?period=today&search=Boris')
            ->assertStatus(200)
            ->json();
        $this->assertCount(1, $res['managers']);
        $this->assertEquals('Manager Boris', $res['managers'][0]['name']);

        // Filter by with_orders
        $res = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/managers-kpi?period=today&filter=with_orders')
            ->assertStatus(200)
            ->json();
        $this->assertContains('Manager Alex', array_column($res['managers'], 'name'));
        $this->assertNotContains('Manager Boris', array_column($res['managers'], 'name'));

        // Filter by without_orders
        $res = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/managers-kpi?period=today&filter=without_orders')
            ->assertStatus(200)
            ->json();
        $this->assertContains('Manager Boris', array_column($res['managers'], 'name'));
        $this->assertNotContains('Manager Alex', array_column($res['managers'], 'name'));

        // Pagination
        $res = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/managers-kpi?period=today&page=1&per_page=1')
            ->assertStatus(200)
            ->json();
        $this->assertCount(1, $res['managers']);
        $this->assertTrue($res['pagination']['has_more']);
        $this->assertEquals(1, $res['pagination']['page']);
        $this->assertEquals(1, $res['pagination']['per_page']);
    }

    public function test_server_side_couriers_kpi_search_filter_sort_and_pagination(): void
    {
        $courier2 = User::create([
            'system_name' => 'Courier Egor',
            'telegram_user_id' => 10010,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $res = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/couriers-kpi?period=today&search=Egor')
            ->assertStatus(200)
            ->json();
        $this->assertCount(1, $res['couriers']);
        $this->assertEquals('Courier Egor', $res['couriers'][0]['name']);

        $res = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/couriers-kpi?period=today&filter=without_deliveries')
            ->assertStatus(200)
            ->json();
        $this->assertContains('Courier Egor', array_column($res['couriers'], 'name'));

        $res = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/couriers-kpi?period=today&page=1&per_page=1')
            ->assertStatus(200)
            ->json();
        $this->assertCount(1, $res['couriers']);
        $this->assertTrue($res['pagination']['has_more']);
        $this->assertEquals(1, $res['pagination']['remaining']);
    }

    public function test_managers_and_couriers_kpi_with_custom_date_range(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 1500,
            'purchase_price' => 500,
        ]);

        $order->delivery()->create([
            'type' => DeliveryType::COURIER,
            'user_id' => $this->courier->id,
            'delivery_cost' => 300,
            'delivery_date' => Carbon::parse('2026-10-05'),
        ]);

        ActionLog::create([
            'actor_id' => $this->manager->id,
            'order_id' => $order->id,
            'action_type' => ActionType::STATUS_CHANGE,
            'field_name' => 'status',
            'old_value' => OrderStatus::ACCEPTED->value,
            'new_value' => OrderStatus::COMPLETED->value,
            'created_at' => Carbon::parse('2026-10-05 11:00:00'),
        ]);

        $res = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/managers-kpi?period=custom&from=2026-10-01&to=2026-10-10')
            ->assertStatus(200)
            ->json();

        $managerStat = collect($res['managers'])->firstWhere('id', $this->manager->id);
        $this->assertNotNull($managerStat);
        $this->assertEquals(1, $managerStat['accepted_orders']);

        $resEmpty = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/managers-kpi?period=custom&from=2026-10-11&to=2026-10-15')
            ->assertStatus(200)
            ->json();
        $managerStatEmpty = collect($resEmpty['managers'])->firstWhere('id', $this->manager->id);
        $this->assertEquals(0, $managerStatEmpty['accepted_orders']);

        $resCourier = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/couriers-kpi?period=custom&from=2026-10-01&to=2026-10-10')
            ->assertStatus(200)
            ->json();
        $courierStat = collect($resCourier['couriers'])->firstWhere('id', $this->courier->id);
        $this->assertNotNull($courierStat);
        $this->assertEquals(1, $courierStat['completed_deliveries']);

        Carbon::setTestNow();
    }

    public function test_financial_history_with_custom_date_range(): void
    {
        FinancialStat::create([
            'site_id' => $this->site->id,
            'date' => '2026-10-02',
            'total_revenue' => 10000,
            'total_purchase' => 4000,
            'courier_payouts' => 1500,
            'net_profit' => 4500,
        ]);

        FinancialStat::create([
            'site_id' => $this->site->id,
            'date' => '2026-10-04',
            'total_revenue' => 15000,
            'total_purchase' => 6000,
            'courier_payouts' => 2000,
            'net_profit' => 7000,
        ]);

        $res = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/financial-history?metric=total_revenue&from=2026-10-01&to=2026-10-05')
            ->assertStatus(200)
            ->json();

        $this->assertEquals('total_revenue', $res['metric']);
        $this->assertEquals(['01.10', '02.10', '03.10', '04.10', '05.10'], $res['labels']);
        $this->assertEquals([0.0, 10000.0, 0.0, 15000.0, 0.0], $res['values']);
    }
}
