<?php

namespace Tests\Feature\Tma;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\StaleOrderReminderService;
use App\Services\SettingService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaleOrderReminderTest extends TestCase
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
            'telegram_user_id' => 90001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Olga',
            'telegram_user_id' => 90002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Pavel',
            'telegram_user_id' => 90003,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Store 1',
            'domain' => 'store.example.com',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Test Client',
            'phone' => '+375291112233',
            'address' => 'Минск, ул. Ленина, 10',
        ]);

        Cache::flush();
    }

    public function test_stale_assigned_order_reminds_assigned_courier(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-STALE-001',
            'total_price' => 150,
            'purchase_price' => 100,
            'status' => OrderStatus::ASSIGNED,
            'manager_id' => $this->manager->id,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'user_id' => $this->courier->id,
            'type' => DeliveryType::COURIER,
            'delivery_address' => 'Минск, ул. Ленина, 10',
        ]);

        // Simulate 3 hours elapsed
        Order::where('id', $order->id)->update([
            'updated_at' => now()->subHours(3),
        ]);

        $notifiedUserIds = [];
        $telegramMock = $this->mock(TelegramService::class);
        $telegramMock->shouldReceive('notifyStaleOrder')
            ->andReturnUsing(function (Order $o, User $recipient, string $duration) use (&$notifiedUserIds) {
                $notifiedUserIds[] = $recipient->id;
            });

        $service = app(StaleOrderReminderService::class);
        $count = $service->checkAndRemind(120);

        $this->assertEquals(2, $count);
        $this->assertContains($this->courier->id, $notifiedUserIds);
        $this->assertContains($this->manager->id, $notifiedUserIds);
    }

    public function test_user_can_disable_stale_order_reminders_in_settings(): void
    {
        $settingService = app(SettingService::class);
        // Courier disables stale order reminders
        $settingService->set(SettingKey::NOTIFY_STALE_ORDERS, false, 'user', (string) $this->courier->id);

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-STALE-002',
            'total_price' => 100,
            'purchase_price' => 50,
            'status' => OrderStatus::AT_WAREHOUSE,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'user_id' => $this->courier->id,
            'type' => DeliveryType::COURIER,
            'delivery_address' => 'Минск, ул. Ленина, 10',
        ]);

        Order::where('id', $order->id)->update([
            'updated_at' => now()->subHours(4),
        ]);

        $telegramMock = $this->mock(TelegramService::class);
        $telegramMock->shouldNotReceive('notifyStaleOrder');

        $service = app(StaleOrderReminderService::class);
        $count = $service->checkAndRemind(120);

        $this->assertEquals(0, $count);
    }

    public function test_stale_order_reminder_is_not_sent_again_within_cooldown(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-STALE-003',
            'total_price' => 120,
            'purchase_price' => 80,
            'status' => OrderStatus::ASSIGNED,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'user_id' => $this->courier->id,
            'type' => DeliveryType::COURIER,
            'delivery_address' => 'Минск, ул. Ленина, 10',
        ]);

        Order::where('id', $order->id)->update([
            'updated_at' => now()->subHours(2),
        ]);

        $notifiedCount = 0;
        $telegramMock = $this->mock(TelegramService::class);
        $telegramMock->shouldReceive('notifyStaleOrder')
            ->andReturnUsing(function () use (&$notifiedCount) {
                $notifiedCount++;
            });

        $service = app(StaleOrderReminderService::class);
        $firstCount = $service->checkAndRemind(60);
        $this->assertEquals(1, $firstCount);
        $this->assertEquals(1, $notifiedCount);

        // Second run immediately after should be skipped by cooldown cache
        $secondCount = $service->checkAndRemind(60);
        $this->assertEquals(0, $secondCount);
    }

    public function test_completed_or_cancelled_orders_are_not_reminded(): void
    {
        $completedOrder = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-COMPLETED',
            'total_price' => 200,
            'purchase_price' => 100,
            'status' => OrderStatus::COMPLETED,
        ]);

        $cancelledOrder = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-CANCELLED',
            'total_price' => 200,
            'purchase_price' => 100,
            'status' => OrderStatus::CANCELLED,
        ]);

        Order::whereIn('id', [$completedOrder->id, $cancelledOrder->id])->update([
            'updated_at' => now()->subDays(5),
        ]);

        $telegramMock = $this->mock(TelegramService::class);
        $telegramMock->shouldNotReceive('notifyStaleOrder');

        $service = app(StaleOrderReminderService::class);
        $count = $service->checkAndRemind(60);
        $this->assertEquals(0, $count);
    }

    public function test_artisan_command_orders_remind_stale(): void
    {
        $telegramMock = $this->mock(TelegramService::class);
        $telegramMock->shouldReceive('notifyStaleOrder')->zeroOrMoreTimes();

        $exitCode = Artisan::call('orders:remind-stale', ['--minutes' => 60]);
        $this->assertEquals(0, $exitCode);
    }

    public function test_settings_api_for_notify_stale_orders(): void
    {
        // Manager checks default setting
        $res = $this->actingAs($this->manager)
            ->getJson('/api/settings/notify_stale_orders');
        $res->assertOk()
            ->assertJson([
                'key' => 'notify_stale_orders',
                'value' => true,
            ]);

        // Manager disables notify_stale_orders for themselves
        $updateRes = $this->actingAs($this->manager)
            ->postJson('/api/settings/notify_stale_orders', [
                'value' => false,
                'scope' => 'user',
            ]);
        $updateRes->assertOk()
            ->assertJson([
                'success' => true,
                'key' => 'notify_stale_orders',
                'value' => false,
            ]);

        // Courier checks their own setting is still true (user-scoped)
        $courierRes = $this->actingAs($this->courier)
            ->getJson('/api/settings/notify_stale_orders');
        $courierRes->assertOk()
            ->assertJson([
                'key' => 'notify_stale_orders',
                'value' => true,
            ]);
    }

    public function test_courier_can_access_tma_settings_page(): void
    {
        $response = $this->actingAs($this->courier)->get('/tma/settings');
        $response->assertOk();
    }
}
