<?php

namespace Tests\Feature\Optimization;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\AggregateFinancialStatsJob;
use App\Models\FinancialStat;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\OrderCreator;
use App\Services\Orders\OrderUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderAndFinancialFixesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $courier;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'role' => UserRole::OWNER,
            'system_name' => 'Owner Test',
            'telegram_user_id' => '111111',
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'role' => UserRole::COURIER,
            'system_name' => 'Courier Test',
            'telegram_user_id' => '222222',
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Test Site',
            'domain' => 'test.com',
            'is_active' => true,
        ]);
    }

    public function test_deliveries_and_payments_have_order_id_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('deliveries', 'deliveries_order_id_index'));
        $this->assertTrue(Schema::hasIndex('payments', 'payments_order_id_index'));
    }

    public function test_order_creator_and_updater_sync_courier_id(): void
    {
        $creator = app(OrderCreator::class);
        $order = $creator->create($this->site->id, [
            'delivery_courier_id' => $this->courier->id,
            'total_price' => 200,
            'purchase_price' => 50,
        ], $this->owner->id);

        $this->assertEquals($this->courier->id, $order->courier_id);

        $updater = app(OrderUpdater::class);
        $updated = $updater->update($order->id, [
            'delivery_courier_id' => null,
        ], $this->owner->id);

        $this->assertNull($updated->courier_id);
    }

    public function test_financial_stats_reset_to_zero_when_orders_cancelled(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 500.00,
            'purchase_price' => 200.00,
            'status' => OrderStatus::ACCEPTED,
            'created_at' => now(),
        ]);

        AggregateFinancialStatsJob::dispatchSync(now()->toDateString());

        $stat = FinancialStat::where('site_id', $this->site->id)
            ->where('date', now()->toDateString())
            ->first();

        $this->assertNotNull($stat);
        $this->assertEquals(500.00, (float) $stat->total_revenue);

        // Cancel order
        $order->update(['status' => OrderStatus::CANCELLED]);

        AggregateFinancialStatsJob::dispatchSync(now()->toDateString());

        $statAfter = FinancialStat::where('site_id', $this->site->id)
            ->where('date', now()->toDateString())
            ->first();

        $this->assertNotNull($statAfter);
        $this->assertEquals(0.00, (float) $statAfter->total_revenue);
        $this->assertEquals(0.00, (float) $statAfter->total_purchase);
        $this->assertEquals(0.00, (float) $statAfter->net_profit);
    }
}
