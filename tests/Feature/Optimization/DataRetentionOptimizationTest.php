<?php

namespace Tests\Feature\Optimization;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\FinancialStat;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataRetentionOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Site $activeSite;

    protected Site $inactiveSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Active Owner',
            'telegram_user_id' => 99101,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->activeSite = Site::create([
            'name' => 'Active Site',
            'domain' => 'active.test',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $this->inactiveSite = Site::create([
            'name' => 'Inactive Site',
            'domain' => 'inactive.test',
            'api_token' => (string) Str::uuid(),
            'is_active' => false,
        ]);
    }

    public function test_inactive_site_orders_are_aggregated_before_deletion(): void
    {
        $oldDate = now()->subDays(60);

        $order = Order::create([
            'site_id' => $this->inactiveSite->id,
            'total_price' => 800,
            'purchase_price' => 400,
            'status' => OrderStatus::COMPLETED,
        ]);
        $order->created_at = $oldDate;
        $order->save();

        $service = app(DataRetentionService::class);
        $service->run(30);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);

        $stat = FinancialStat::where('site_id', $this->inactiveSite->id)
            ->where('date', $oldDate->toDateString())
            ->first();

        $this->assertNotNull($stat);
        $this->assertEquals(800.0, (float) $stat->total_revenue);
        $this->assertEquals(400.0, (float) $stat->total_purchase);
        $this->assertEquals(400.0, (float) $stat->net_profit);
    }

    public function test_inactive_owner_cannot_wipe_tables(): void
    {
        $inactiveOwner = User::create([
            'system_name' => 'Inactive Owner',
            'telegram_user_id' => 99102,
            'role' => UserRole::OWNER,
            'is_active' => false,
        ]);

        $service = app(DataRetentionService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->wipeTables(['orders'], $inactiveOwner->id);
    }

    public function test_wipe_table_names_contains_security_critical_tables(): void
    {
        $tables = DataRetentionService::wipeTableNames();

        $this->assertContains('settings', $tables);
        $this->assertContains('failed_jobs', $tables);
        $this->assertContains('job_batches', $tables);
    }

    public function test_concurrent_retention_run_skips_when_locked(): void
    {
        $lock = Cache::lock('data_retention_running', 600);
        $lock->get();

        $service = app(DataRetentionService::class);

        $oldDate = now()->subDays(45);
        $order = Order::create([
            'site_id' => $this->activeSite->id,
            'total_price' => 200,
            'purchase_price' => 100,
            'status' => OrderStatus::COMPLETED,
        ]);
        $order->created_at = $oldDate;
        $order->save();

        $service->run(30);

        // Should not have deleted order because lock was held
        $this->assertDatabaseHas('orders', ['id' => $order->id]);

        $lock->release();
    }
}
