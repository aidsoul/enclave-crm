<?php

namespace Tests\Feature\Optimization;

use App\Enums\OrderStatus;
use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\OrderStatusMachine;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DatabaseAndCachingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->owner = User::create([
            'system_name' => 'Owner',
            'telegram_user_id' => 99001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager',
            'telegram_user_id' => 99002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Alpha Shop',
            'domain' => 'alpha.com',
            'is_active' => true,
        ]);
    }

    public function test_order_search_uses_ilike_case_insensitive_matching(): void
    {
        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Иван Иванов',
            'phone' => '+375291112233',
            'email' => 'ivan@example.com',
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $customer->id,
            'manager_id' => $this->manager->id,
            'total_price' => 1500,
            'purchase_price' => 700,
            'status' => OrderStatus::ACCEPTED,
        ]);

        // Lowercase search for uppercase Cyrillic name
        $response = $this->actingAs($this->owner)
            ->getJson('/api/orders?search=иван&page=1');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals($order->id, $response->json('orders.0.id'));

        // Partial phone search
        $responsePhone = $this->actingAs($this->owner)
            ->getJson('/api/orders?search=11122&page=1');

        $responsePhone->assertOk();
        $this->assertEquals(1, $responsePhone->json('total'));

        // Scope search test on Order model
        $scopeResults = Order::search('ИВАН')->get();
        $this->assertCount(1, $scopeResults);

        $scopeResultsPhone = Order::search('2911122')->get();
        $this->assertCount(1, $scopeResultsPhone);
    }

    public function test_site_catalog_cached_in_redis_and_invalidated_by_observer(): void
    {
        $this->assertFalse(Cache::has('sites:reference_list'));

        $sites1 = Site::getCachedActive();
        $this->assertTrue(Cache::has('sites:reference_list'));
        $this->assertCount(1, $sites1);

        // Creating another active site should invalidate cache via SiteObserver
        $site2 = Site::create([
            'name' => 'Beta Shop',
            'domain' => 'beta.com',
            'is_active' => true,
        ]);

        $this->assertFalse(Cache::has('sites:reference_list'));

        // Fetching again populates cache with both sites
        $sites2 = Site::getCachedActive();
        $this->assertTrue(Cache::has('sites:reference_list'));
        $this->assertCount(2, $sites2);

        // Updating site domain invalidates cache
        $site2->update(['domain' => 'beta-new.com']);
        $this->assertFalse(Cache::has('sites:reference_list'));

        // Token lookup caching & invalidation (SHA-256 hash key)
        $token = $site2->api_token;
        $hash = hash('sha256', $token);
        $found = Site::findByTokenCached($token);
        $this->assertNotNull($found);
        $this->assertTrue(Cache::has("site:token_hash:{$hash}"));

        // Rotating token invalidates token cache via SiteObserver
        $site2->rotateApiToken();
        $this->assertFalse(Cache::has("site:token_hash:{$hash}"));

        // Deleting site invalidates reference cache
        Site::getCachedActive();
        $this->assertTrue(Cache::has('sites:reference_list'));
        $site2->delete();
        $this->assertFalse(Cache::has('sites:reference_list'));
    }

    public function test_system_settings_cached_in_redis_and_invalidated_by_observer(): void
    {
        $service = app(SettingService::class);

        $cacheKey = 'setting:global:'.SettingKey::RETENTION_DAYS->value;
        $this->assertFalse(Cache::has($cacheKey));

        // Get setting populates cache
        $val1 = $service->get(SettingKey::RETENTION_DAYS);
        $this->assertEquals(SettingKey::RETENTION_DAYS->default(), $val1);
        $this->assertTrue(Cache::has($cacheKey));

        // Setting a new value triggers SettingObserver to invalidate cache
        $service->set(SettingKey::RETENTION_DAYS, 60, 'global');
        $this->assertFalse(Cache::has($cacheKey));

        // Reading again returns new value and caches it
        $val2 = $service->get(SettingKey::RETENTION_DAYS);
        $this->assertEquals(60, $val2);
        $this->assertTrue(Cache::has($cacheKey));

        // User override setting caching and observer invalidation
        $userCacheKey = "setting:user:{$this->manager->id}:".SettingKey::DELETE_CHAT_MESSAGES_ON_ACCEPT->value;
        $this->assertFalse(Cache::has($userCacheKey));

        $userVal1 = $service->get(SettingKey::DELETE_CHAT_MESSAGES_ON_ACCEPT, $this->manager);
        $this->assertTrue(Cache::has($userCacheKey));

        // Update user setting directly via Eloquent
        Setting::updateOrCreate(
            [
                'key' => SettingKey::DELETE_CHAT_MESSAGES_ON_ACCEPT->value,
                'scope' => 'user',
                'user_id' => $this->manager->id,
            ],
            ['value' => '1']
        );

        // SettingObserver should have invalidated user cache key
        $this->assertFalse(Cache::has($userCacheKey));

        $userVal2 = $service->get(SettingKey::DELETE_CHAT_MESSAGES_ON_ACCEPT, $this->manager);
        $this->assertTrue($userVal2);
        $this->assertTrue(Cache::has($userCacheKey));
    }

    public function test_settings_service_runtime_cache_prevents_repeated_cache_queries(): void
    {
        $service = app(SettingService::class);
        $service->clearRuntimeCache();

        $resolved1 = $service->resolve(SettingKey::RETENTION_DAYS, $this->owner);
        $this->assertIsArray($resolved1);
        $this->assertArrayHasKey('value', $resolved1);
        $this->assertArrayHasKey('source', $resolved1);

        // Flushed redis cache while keeping memory cache
        Cache::flush();
        // resolve uses runtime memory cache and still returns the value without querying Redis
        $this->assertEquals($resolved1['value'], $service->get(SettingKey::RETENTION_DAYS, $this->owner));
        $this->assertEquals($resolved1['source'], $service->getSource(SettingKey::RETENTION_DAYS, $this->owner));
    }

    public function test_dashboard_stats_and_recent_orders_response(): void
    {
        Order::create([
            'site_id' => $this->site->id,
            'total_price' => 500,
            'purchase_price' => 250,
            'status' => OrderStatus::ACCEPTED,
        ]);

        $statsResp = $this->actingAs($this->owner)->getJson('/api/dashboard/stats?period=today');
        $statsResp->assertOk();
        $this->assertEquals(500, $statsResp->json('stats.total_revenue'));
        $this->assertEquals(250, $statsResp->json('stats.total_purchase'));

        $recentResp = $this->actingAs($this->owner)->getJson('/api/dashboard/recent-orders');
        $recentResp->assertOk();
        $this->assertNotEmpty($recentResp->json('orders'));
    }

    public function test_order_status_machine_atomic_transitions(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 300,
            'purchase_price' => 150,
            'status' => OrderStatus::ACCEPTED,
        ]);

        $machine = app(OrderStatusMachine::class);
        $updated = $machine->markAtWarehouse($order->id, $this->owner->id);
        $this->assertEquals(OrderStatus::AT_WAREHOUSE, $updated->status);

        $completed = $machine->markComplete($order->id, $this->owner->id);
        $this->assertEquals(OrderStatus::COMPLETED, $completed->status);
    }

    public function test_emergency_wipe_is_throttled(): void
    {
        $this->owner->setWipeConfirmCode('1234');

        for ($i = 0; $i < 10; $i++) {
            $resp = $this->actingAs($this->owner)->postJson('/api/emergency-wipe/initiate', [
                'pin' => '1234',
            ]);
            $this->assertTrue(in_array($resp->status(), [200, 429]));
        }

        $throttledResp = $this->actingAs($this->owner)->postJson('/api/emergency-wipe/initiate', [
            'pin' => '1234',
        ]);
        $throttledResp->assertStatus(429);
    }
}
