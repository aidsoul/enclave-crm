<?php

namespace Tests\Feature\Optimization;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\AggregateFinancialStatsJob;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\FinancialStat;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\DataRetentionService;
use App\Services\Orders\OrderUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class SecurityAndConcurrencyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $owner2;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->owner = User::create([
            'system_name' => 'Owner Alpha',
            'telegram_user_id' => '1001001',
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->owner2 = User::create([
            'system_name' => 'Owner Beta',
            'telegram_user_id' => '1001002',
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Shop Test',
            'domain' => 'shop-test.com',
            'is_active' => true,
        ]);
    }

    public function test_personnel_destroy_forbids_deleting_another_owner(): void
    {
        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/personnel/{$this->owner2->id}");

        $response->assertStatus(422);
        $this->assertEquals('Нельзя удалить владельца', $response->json('error'));
        $this->assertDatabaseHas('users', ['id' => $this->owner2->id]);
    }

    public function test_emergency_wipe_atomic_token_pull_prevents_double_execution(): void
    {
        $this->owner->setWipeConfirmCode('7788');

        $wipeToken = (string) Str::uuid();
        Cache::put('wipe_token:'.$wipeToken, $this->owner->id, now()->addMinutes(10));

        $res1 = $this->actingAs($this->owner)->postJson('/api/emergency-wipe/execute', [
            'wipe_token' => $wipeToken,
            'confirm_code' => '7788',
        ]);
        $res1->assertOk();
        $this->assertTrue($res1->json('success'));

        // Second execution with same token must fail
        $res2 = $this->actingAs($this->owner)->postJson('/api/emergency-wipe/execute', [
            'wipe_token' => $wipeToken,
            'confirm_code' => '7788',
        ]);
        $res2->assertStatus(422);
    }

    public function test_data_retention_excludes_cancelled_orders_and_preserves_payouts(): void
    {
        $oldDate = now()->subDays(40);

        // Completed order
        $validOrder = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 1000,
            'purchase_price' => 600,
            'status' => OrderStatus::COMPLETED,
        ]);
        $validOrder->created_at = $oldDate;
        $validOrder->save();

        Delivery::create([
            'order_id' => $validOrder->id,
            'delivery_cost' => 50,
            'type' => 'courier',
        ]);

        // Cancelled order (should be excluded)
        $cancelledOrder = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 500,
            'purchase_price' => 300,
            'status' => OrderStatus::CANCELLED,
        ]);
        $cancelledOrder->created_at = $oldDate;
        $cancelledOrder->save();

        // Existing stat with unforeseen expense
        FinancialStat::create([
            'site_id' => $this->site->id,
            'date' => $oldDate->toDateString(),
            'total_revenue' => 0,
            'total_purchase' => 0,
            'courier_payouts' => 0,
            'unforeseen_expenses' => 25,
            'net_profit' => -25,
        ]);

        $service = app(DataRetentionService::class);
        $service->run(30);

        $stat = FinancialStat::where('site_id', $this->site->id)
            ->where('date', $oldDate->toDateString())
            ->first();

        $this->assertNotNull($stat);
        $this->assertEquals(1000.0, (float) $stat->total_revenue);
        $this->assertEquals(600.0, (float) $stat->total_purchase);
        $this->assertEquals(50.0, (float) $stat->courier_payouts);
        $this->assertEquals(25.0, (float) $stat->unforeseen_expenses);
        // net_profit = 1000 - 600 - 50 - 25 = 325
        $this->assertEquals(325.0, (float) $stat->net_profit);
    }

    public function test_telegram_webhook_secret_token_verification(): void
    {
        Config::set('nutgram.secret_token', 'super_secret_webhook_token_123');

        // Request without secret token
        $respUnauthorized = $this->postJson('/api/telegram/webhook', [
            'update_id' => 12345,
            'message' => ['text' => 'test'],
        ]);
        $respUnauthorized->assertStatus(401);

        // Request with invalid secret token
        $respInvalid = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong_token')
            ->postJson('/api/telegram/webhook', [
                'update_id' => 12345,
                'message' => ['text' => 'test'],
            ]);
        $respInvalid->assertStatus(401);

        // Request with correct secret token
        $respAuthorized = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'super_secret_webhook_token_123')
            ->postJson('/api/telegram/webhook', [
                'update_id' => 12345,
                'message' => ['text' => 'test'],
            ]);
        $respAuthorized->assertOk();
    }

    public function test_telegram_webhook_rejected_when_secret_token_not_configured(): void
    {
        Config::set('nutgram.secret_token', null);

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'any_token')
            ->postJson('/api/telegram/webhook', [
                'update_id' => 12345,
                'message' => ['text' => 'test'],
            ]);
        $response->assertStatus(401);
    }

    public function test_telegram_set_webhook_command_passes_secret_token(): void
    {
        Config::set('nutgram.secret_token', 'my_custom_secret_token');
        Config::set('services.telegram.webhook_url', null);
        Config::set('app.url', 'https://crm.example.com');

        $mockBot = \Mockery::mock(Nutgram::class);
        $mockBot->shouldReceive('setWebhook')
            ->once()
            ->withArgs(function ($url, $cert, $ip, $max, $allowed, $drop, $secret) {
                return $url === 'https://crm.example.com/api/telegram/webhook'
                    && $secret === 'my_custom_secret_token';
            })
            ->andReturn(true);

        $this->app->instance(Nutgram::class, $mockBot);

        $this->artisan('telegram:set-webhook')
            ->assertSuccessful();
    }

    public function test_telegram_set_webhook_command_passes_cli_option_secret(): void
    {
        Config::set('nutgram.secret_token', null);
        Config::set('services.telegram.webhook_url', null);
        Config::set('app.url', 'https://crm.example.com');

        $mockBot = \Mockery::mock(Nutgram::class);
        $mockBot->shouldReceive('setWebhook')
            ->once()
            ->withArgs(function ($url, $cert, $ip, $max, $allowed, $drop, $secret) {
                return $url === 'https://crm.example.com/api/telegram/webhook'
                    && $secret === 'cli_provided_secret_token';
            })
            ->andReturn(true);

        $this->app->instance(Nutgram::class, $mockBot);

        $this->artisan('telegram:set-webhook', ['--secret' => 'cli_provided_secret_token'])
            ->assertSuccessful();
    }

    public function test_telegram_set_webhook_command_fails_without_secret_token(): void
    {
        Config::set('nutgram.secret_token', null);

        $this->artisan('telegram:set-webhook')
            ->expectsOutputToContain('A secret token is required')
            ->assertFailed();
    }

    public function test_telegram_set_webhook_command_avoids_duplicate_url_suffix(): void
    {
        Config::set('nutgram.secret_token', 'token_123');
        Config::set('services.telegram.webhook_url', 'https://crm.example.com/api/telegram/webhook');

        $mockBot = \Mockery::mock(Nutgram::class);
        $mockBot->shouldReceive('setWebhook')
            ->once()
            ->withArgs(function ($url, $cert, $ip, $max, $allowed, $drop, $secret) {
                return $url === 'https://crm.example.com/api/telegram/webhook'
                    && $secret === 'token_123';
            })
            ->andReturn(true);

        $this->app->instance(Nutgram::class, $mockBot);

        $this->artisan('telegram:set-webhook')
            ->assertSuccessful();
    }

    public function test_telegram_set_webhook_command_handles_drop_pending_updates(): void
    {
        Config::set('nutgram.secret_token', 'token_123');
        Config::set('services.telegram.webhook_url', null);
        Config::set('app.url', 'https://crm.example.com');

        $mockBot = \Mockery::mock(Nutgram::class);
        $mockBot->shouldReceive('setWebhook')
            ->once()
            ->withArgs(function ($url, $cert, $ip, $max, $allowed, $drop, $secret) {
                return $url === 'https://crm.example.com/api/telegram/webhook'
                    && $drop === true
                    && $secret === 'token_123';
            })
            ->andReturn(true);

        $this->app->instance(Nutgram::class, $mockBot);

        $this->artisan('telegram:set-webhook', ['--drop-pending-updates' => true])
            ->assertSuccessful();
    }

    public function test_telegram_set_webhook_command_rejects_invalid_secret_token_format(): void
    {
        $this->artisan('telegram:set-webhook', ['--secret' => 'invalid secret with spaces!'])
            ->expectsOutputToContain('Invalid secret token format')
            ->assertFailed();
    }

    public function test_order_updater_rejects_customer_belonging_to_another_site(): void
    {
        $site2 = Site::create([
            'name' => 'Shop 2',
            'domain' => 'shop2-test.com',
            'is_active' => true,
        ]);

        $foreignCustomer = Customer::create([
            'site_id' => $site2->id,
            'name' => 'Foreign Customer',
            'phone' => '+79998887766',
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 500,
            'purchase_price' => 200,
            'status' => OrderStatus::CREATED,
        ]);

        $updater = app(OrderUpdater::class);

        $this->expectException(\InvalidArgumentException::class);
        $updater->update($order->id, [
            'customer_id' => $foreignCustomer->id,
        ], $this->owner->id);
    }

    public function test_settings_scope_role_requires_owner_role(): void
    {
        $manager = User::create([
            'system_name' => 'Manager Alex',
            'telegram_user_id' => '1001003',
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)
            ->postJson('/api/settings/retention_days', [
                'scope' => 'role',
                'value' => 60,
            ]);

        $response->assertStatus(403);
    }

    public function test_dashboard_financial_history_caps_days_at_365(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/financial-history?days=9999');

        $response->assertOk();
        $this->assertCount(366, $response->json('labels'));
    }

    public function test_personnel_search_is_case_insensitive(): void
    {
        $user = User::create([
            'system_name' => 'Mikhail Sergeyevich',
            'telegram_user_id' => '1001004',
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $responseLower = $this->actingAs($this->owner)
            ->getJson('/api/personnel?search=mikhail');
        $responseLower->assertOk();
        $this->assertContains($user->id, array_column($responseLower->json('personnel'), 'id'));

        $responseUpper = $this->actingAs($this->owner)
            ->getJson('/api/personnel?search=MIKHAIL');
        $responseUpper->assertOk();
        $this->assertContains($user->id, array_column($responseUpper->json('personnel'), 'id'));
    }

    public function test_logout_removes_token_from_user_sessions_set(): void
    {
        $user = User::create([
            'system_name' => 'Courier Dan',
            'telegram_user_id' => '1001005',
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $token = 'test_token_user_sessions_set_123';
        Redis::sadd("user_sessions:{$user->id}", $token);
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $user->id,
            'telegram_user_id' => $user->telegram_user_id,
            'role' => $user->role->value,
        ]));

        $this->assertTrue(in_array($token, Redis::smembers("user_sessions:{$user->id}")));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertFalse(in_array($token, Redis::smembers("user_sessions:{$user->id}")));
    }

    public function test_dashboard_financial_history_handles_invalid_date_format(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/financial-history?from=invalid-date&to=bad-date');

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Неверный формат даты для параметров from или to.');
    }

    public function test_dashboard_stats_excludes_pickup_deliveries_from_courier_payouts(): void
    {
        $site = Site::create([
            'name' => 'Pickup Site',
            'domain' => 'pickup.example.com',
            'is_active' => true,
        ]);

        $order = Order::create([
            'site_id' => $site->id,
            'total_price' => 500.00,
            'purchase_price' => 200.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $order->delivery()->create([
            'type' => DeliveryType::PICKUP,
            'delivery_cost' => 50.00,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/stats?period=today');

        $response->assertOk()
            ->assertJsonPath('stats.total_revenue', 500)
            ->assertJsonPath('stats.total_purchase', 200)
            ->assertJsonPath('stats.courier_payouts', 0)
            ->assertJsonPath('stats.net_profit', 300);
    }

    public function test_courier_shift_summary_returns_all_for_owner_when_no_id_given(): void
    {
        $courier = User::create([
            'system_name' => 'Courier Shift Tester',
            'telegram_user_id' => '1001099',
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/courier/shift-summary');

        $response->assertOk()
            ->assertJsonStructure(['period', 'summary', 'couriers']);
    }

    public function test_dashboard_stats_bounds_historical_range_to_requested_dates(): void
    {
        $site = Site::create([
            'name' => 'Active Site',
            'domain' => 'active.example.com',
            'is_active' => true,
        ]);

        Order::create([
            'site_id' => $site->id,
            'total_price' => 100.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::COMPLETED,
            'created_at' => now(),
        ]);

        FinancialStat::create([
            'site_id' => $site->id,
            'date' => '2026-10-01',
            'total_revenue' => 1000.00,
            'total_purchase' => 400.00,
            'courier_payouts' => 100.00,
            'net_profit' => 500.00,
        ]);

        FinancialStat::create([
            'site_id' => $site->id,
            'date' => '2026-10-10',
            'total_revenue' => 2000.00,
            'total_purchase' => 800.00,
            'courier_payouts' => 200.00,
            'net_profit' => 1000.00,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/stats?period=custom&from=2026-10-01&to=2026-10-05');

        $response->assertOk()
            ->assertJsonPath('stats.total_revenue', 1000)
            ->assertJsonPath('stats.total_purchase', 400)
            ->assertJsonPath('stats.courier_payouts', 100)
            ->assertJsonPath('stats.net_profit', 500);
    }

    public function test_aggregate_financial_stats_job_excludes_pickup_deliveries(): void
    {
        $site = Site::create([
            'name' => 'Pickup Site 2',
            'domain' => 'pickup2.example.com',
            'is_active' => true,
        ]);

        $order = Order::create([
            'site_id' => $site->id,
            'total_price' => 600.00,
            'purchase_price' => 300.00,
            'status' => OrderStatus::COMPLETED,
        ]);
        $order->created_at = '2026-10-14 12:00:00';
        $order->save();

        $order->delivery()->create([
            'type' => DeliveryType::PICKUP,
            'delivery_cost' => 80.00,
        ]);

        (new AggregateFinancialStatsJob('2026-10-14'))->handle();

        $stat = FinancialStat::where('site_id', $site->id)->where('date', '2026-10-14')->first();
        $this->assertNotNull($stat);
        $this->assertEquals(0.00, (float) $stat->courier_payouts);
        $this->assertEquals(300.00, (float) $stat->net_profit);
    }

    public function test_financial_history_filters_inactive_sites(): void
    {
        $activeSite = Site::create([
            'name' => 'Active Site',
            'domain' => 'history-active.example.com',
            'is_active' => true,
        ]);

        $inactiveSite = Site::create([
            'name' => 'Inactive Site',
            'domain' => 'history-inactive.example.com',
            'is_active' => false,
        ]);

        FinancialStat::create([
            'site_id' => $activeSite->id,
            'date' => '2026-10-01',
            'total_revenue' => 1500.00,
            'total_purchase' => 500.00,
            'courier_payouts' => 200.00,
            'net_profit' => 800.00,
        ]);

        FinancialStat::create([
            'site_id' => $inactiveSite->id,
            'date' => '2026-10-01',
            'total_revenue' => 3000.00,
            'total_purchase' => 1000.00,
            'courier_payouts' => 500.00,
            'net_profit' => 1500.00,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/financial-history?from=2026-10-01&to=2026-10-01&metric=total_revenue');

        $response->assertOk();
        $this->assertEquals([1500.0], $response->json('values'));
    }
}
