<?php

namespace Tests\Feature\Queue;

use App\Enums\UserRole;
use App\Jobs\AggregateDailyUserKpisJob;
use App\Jobs\AggregateFinancialStatsJob;
use App\Jobs\BurnArchivesJob;
use App\Jobs\CheckStaleOrdersJob;
use App\Jobs\DataRetentionJob;
use App\Jobs\DeleteOrderChatMessagesJob;
use App\Jobs\NotifyCourierJob;
use App\Jobs\ParseSiteWebhookJob;
use App\Jobs\ProcessIncomingLeadJob;
use App\Jobs\ProcessIncomingOrderJob;
use App\Jobs\PurgeAllBackupsJob;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_cannot_access_horizon(): void
    {
        $manager = User::create([
            'system_name' => 'Manager User',
            'telegram_user_id' => 12345,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->actingAs($manager);
        $this->assertFalse(Gate::check('viewHorizon', [$manager]));

        $response = $this->get('/horizon');
        $response->assertStatus(403);
    }

    public function test_owner_can_access_horizon(): void
    {
        $owner = User::create([
            'system_name' => 'Owner User',
            'telegram_user_id' => 54321,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner);
        $this->assertTrue(Gate::check('viewHorizon', [$owner]));

        $response = $this->get('/horizon');
        $response->assertOk();
    }

    public function test_horizon_supervisor_configuration_has_dedicated_queues(): void
    {
        $defaults = config('horizon.defaults');

        $this->assertArrayHasKey('supervisor-1', $defaults);
        $this->assertArrayHasKey('supervisor-low', $defaults);

        $this->assertEquals(['high', 'notifications', 'default'], $defaults['supervisor-1']['queue']);
        $this->assertSame('redis', $defaults['supervisor-1']['connection']);

        $this->assertEquals(['low'], $defaults['supervisor-low']['queue']);
        $this->assertSame('redis-low', $defaults['supervisor-low']['connection']);

        $this->assertSame(180, config('queue.connections.redis.retry_after'));
        $this->assertSame(1850, config('queue.connections.redis-low.retry_after'));
    }

    public function test_jobs_are_assigned_to_expected_queues(): void
    {
        $this->assertSame('high', (new ProcessIncomingOrderJob(1, []))->queue);
        $this->assertSame('high', (new ProcessIncomingLeadJob(1, []))->queue);
        $this->assertSame('high', (new PurgeAllBackupsJob)->queue);
        $this->assertSame('redis', (new PurgeAllBackupsJob)->connection);

        $this->assertSame('notifications', (new SendOrderNotificationJob(new Order))->queue);
        $this->assertSame('notifications', (new NotifyCourierJob(new Order, new User))->queue);
        $this->assertSame('notifications', (new DeleteOrderChatMessagesJob(1))->queue);

        $statsJob = new AggregateFinancialStatsJob;
        $this->assertSame('low', $statsJob->queue);
        $this->assertSame('redis-low', $statsJob->connection);

        $kpisJob = new AggregateDailyUserKpisJob;
        $this->assertSame('low', $kpisJob->queue);
        $this->assertSame('redis-low', $kpisJob->connection);

        $burnJob = new BurnArchivesJob(1);
        $this->assertSame('low', $burnJob->queue);
        $this->assertSame('redis-low', $burnJob->connection);

        $staleJob = new CheckStaleOrdersJob;
        $this->assertSame('low', $staleJob->queue);
        $this->assertSame('redis-low', $staleJob->connection);

        $retentionJob = new DataRetentionJob;
        $this->assertSame('low', $retentionJob->queue);
        $this->assertSame('redis-low', $retentionJob->connection);
    }
}
