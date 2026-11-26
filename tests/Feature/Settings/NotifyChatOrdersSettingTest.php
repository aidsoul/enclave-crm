<?php

namespace Tests\Feature\Settings;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\OrderStatus;
use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\SettingService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Tests\TestCase;

class NotifyChatOrdersSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected User $courier;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'name' => 'Test Shop',
            'domain' => 'shop.test',
            'token' => 'test-shop-token-123456789012345',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 91001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Olga',
            'telegram_user_id' => 91002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Pavel',
            'telegram_user_id' => 91003,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);
    }

    public function test_notify_chat_orders_defaults_to_true_for_all_roles(): void
    {
        $settingService = app(SettingService::class);

        $this->assertTrue($settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $this->owner));
        $this->assertTrue($settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $this->manager));
        $this->assertTrue($settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $this->courier));

        $this->actingAs($this->owner)
            ->getJson('/api/settings/notify_chat_orders')
            ->assertOk()
            ->assertJson([
                'key' => 'notify_chat_orders',
                'value' => true,
            ]);

        $this->actingAs($this->manager)
            ->getJson('/api/settings/notify_chat_orders')
            ->assertOk()
            ->assertJson([
                'key' => 'notify_chat_orders',
                'value' => true,
            ]);

        $this->actingAs($this->courier)
            ->getJson('/api/settings/notify_chat_orders')
            ->assertOk()
            ->assertJson([
                'key' => 'notify_chat_orders',
                'value' => true,
            ]);
    }

    public function test_courier_can_update_own_notify_chat_orders_setting(): void
    {
        $response = $this->actingAs($this->courier)
            ->postJson('/api/settings/notify_chat_orders', [
                'value' => false,
                'scope' => 'user',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'key' => 'notify_chat_orders',
                'value' => false,
            ]);

        $settingService = app(SettingService::class);
        $this->assertFalse($settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $this->courier));
        $this->assertTrue($settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $this->manager));
    }

    public function test_user_cache_is_invalidated_when_setting_is_updated(): void
    {
        $settingService = app(SettingService::class);

        $this->assertTrue($settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $this->courier));

        $this->actingAs($this->courier)
            ->postJson('/api/settings/notify_chat_orders', [
                'value' => false,
                'scope' => 'user',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'key' => 'notify_chat_orders',
                'value' => false,
            ]);

        $this->assertFalse($settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $this->courier));
    }

    public function test_courier_forbidden_from_updating_global_scope(): void
    {
        $this->actingAs($this->courier)
            ->postJson('/api/settings/notify_chat_orders', [
                'value' => false,
                'scope' => 'global',
            ])
            ->assertForbidden();
    }

    public function test_notify_owners_about_new_user_respects_setting(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::NOTIFY_CHAT_ORDERS, false, 'user', (string) $this->owner->id);

        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->never())->method('sendMessage');

        $newUser = User::create([
            'system_name' => 'New User',
            'telegram_user_id' => 91099,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $service = new TelegramService($botMock);
        $service->notifyOwnersAboutNewUser($newUser, null);
    }

    public function test_notify_managers_about_new_order_respects_setting(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::NOTIFY_CHAT_ORDERS, false, 'user', (string) $this->manager->id);

        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->once())
            ->method('sendMessage')
            ->with(
                $this->anything(),
                $this->equalTo((string) $this->owner->telegram_user_id)
            );

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => null,
            'total_price' => 200.00,
            'purchase_price' => 150.00,
            'status' => OrderStatus::CREATED,
        ]);

        $service = new TelegramService($botMock);
        $service->notifyManagersAboutNewOrder($order);
    }

    public function test_notify_managers_about_new_order_includes_accept_and_view_buttons(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::NOTIFY_CHAT_ORDERS, false, 'user', (string) $this->owner->id);

        $sentKeyboard = null;
        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function (...$args) use (&$sentKeyboard) {
                // Check all args
                foreach ($args as $key => $val) {
                    if ($val instanceof InlineKeyboardMarkup) {
                        $sentKeyboard = $val;
                    }
                }

                return null;
            });

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => null,
            'total_price' => 300.00,
            'purchase_price' => 200.00,
            'status' => OrderStatus::CREATED,
        ]);

        $service = new TelegramService($botMock);
        $service->notifyManagersAboutNewOrder($order);

        $this->assertNotNull($sentKeyboard);
        $this->assertCount(2, $sentKeyboard->inline_keyboard);
        $this->assertEquals('✅ Принять заказ', $sentKeyboard->inline_keyboard[0][0]->text);
        $this->assertEquals("manager_order:{$order->id}:accept", $sentKeyboard->inline_keyboard[0][0]->callback_data);
        $this->assertEquals('👁 Посмотреть', $sentKeyboard->inline_keyboard[1][0]->text);
        $this->assertStringEndsWith("/tma/orders/{$order->id}", $sentKeyboard->inline_keyboard[1][0]->web_app->url);
    }

    public function test_notify_courier_about_assignment_respects_setting(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::NOTIFY_CHAT_ORDERS, false, 'user', (string) $this->courier->id);

        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->never())->method('sendMessage');

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 100.00,
            'purchase_price' => 70.00,
            'status' => OrderStatus::ASSIGNED,
        ]);

        $service = new TelegramService($botMock);
        $service->notifyCourierAboutAssignment($order, $this->courier);
    }

    public function test_notify_stale_order_respects_setting(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::NOTIFY_CHAT_ORDERS, false, 'user', (string) $this->courier->id);

        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->never())->method('sendMessage');

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 120.00,
            'purchase_price' => 90.00,
            'status' => OrderStatus::ASSIGNED,
        ]);

        $service = new TelegramService($botMock);
        $service->notifyStaleOrder($order, $this->courier, '2 ч.');
    }

    public function test_notify_new_orders_available_and_updatable_for_courier(): void
    {
        $settingService = app(SettingService::class);
        $this->assertTrue($settingService->get(SettingKey::NOTIFY_NEW_ORDERS, $this->courier));

        $this->actingAs($this->courier)
            ->getJson('/api/settings/notify_new_orders')
            ->assertOk()
            ->assertJson([
                'key' => 'notify_new_orders',
                'value' => true,
            ]);

        $this->actingAs($this->courier)
            ->postJson('/api/settings/notify_new_orders', [
                'value' => false,
                'scope' => 'user',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'key' => 'notify_new_orders',
                'value' => false,
            ]);

        $this->assertFalse($settingService->get(SettingKey::NOTIFY_NEW_ORDERS, $this->courier));
    }

    public function test_notify_chat_leads_accessible_to_owner_and_manager_but_not_courier(): void
    {
        $settingService = app(SettingService::class);
        $this->assertTrue($settingService->get(SettingKey::NOTIFY_CHAT_LEADS, $this->owner));
        $this->assertTrue($settingService->get(SettingKey::NOTIFY_CHAT_LEADS, $this->manager));

        // Owner can view and update
        $this->actingAs($this->owner)
            ->getJson('/api/settings/notify_chat_leads')
            ->assertOk()
            ->assertJson([
                'key' => 'notify_chat_leads',
                'value' => true,
            ]);

        $this->actingAs($this->owner)
            ->postJson('/api/settings/notify_chat_leads', [
                'value' => false,
                'scope' => 'user',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'key' => 'notify_chat_leads',
                'value' => false,
            ]);

        $this->assertFalse($settingService->get(SettingKey::NOTIFY_CHAT_LEADS, $this->owner));

        // Manager can view and update
        $this->actingAs($this->manager)
            ->getJson('/api/settings/notify_chat_leads')
            ->assertOk()
            ->assertJson([
                'key' => 'notify_chat_leads',
                'value' => true,
            ]);

        $this->actingAs($this->manager)
            ->postJson('/api/settings/notify_chat_leads', [
                'value' => false,
                'scope' => 'user',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'key' => 'notify_chat_leads',
                'value' => false,
            ]);

        $this->assertFalse($settingService->get(SettingKey::NOTIFY_CHAT_LEADS, $this->manager));

        // Courier cannot access notify_chat_leads
        $this->actingAs($this->courier)
            ->getJson('/api/settings/notify_chat_leads')
            ->assertForbidden();

        $this->actingAs($this->courier)
            ->postJson('/api/settings/notify_chat_leads', [
                'value' => false,
                'scope' => 'user',
            ])
            ->assertForbidden();

        // Owner sees notify_chat_leads in general settings list
        $response = $this->actingAs($this->owner)->getJson('/api/settings');
        $response->assertOk();
        $this->assertArrayHasKey('notify_chat_leads', $response->json('settings'));

        // Manager sees notify_chat_leads in general settings list
        $response = $this->actingAs($this->manager)->getJson('/api/settings');
        $response->assertOk();
        $this->assertArrayHasKey('notify_chat_leads', $response->json('settings'));

        // Courier does not see notify_chat_leads in general settings list
        $response = $this->actingAs($this->courier)->getJson('/api/settings');
        $response->assertOk();
        $this->assertArrayNotHasKey('notify_chat_leads', $response->json('settings'));
    }

    public function test_lead_notification_sent_when_both_master_and_lead_settings_enabled(): void
    {
        $lead = Lead::create([
            'site_id' => $this->site->id,
            'type' => LeadType::ONE_CLICK,
            'status' => LeadStatus::NEW,
            'name' => 'Alice',
            'phone' => '+375291112233',
            'product_name' => 'Sneakers',
        ]);

        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->exactly(2))
            ->method('sendMessage')
            ->with($this->callback(function ($text) {
                return str_contains($text, 'Новая заявка с сайта') && str_contains($text, 'Sneakers');
            }));

        $service = new TelegramService($botMock);
        $service->notifyManagersAboutNewLead($lead);
    }

    public function test_lead_notification_skipped_when_master_notify_chat_orders_is_false(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::NOTIFY_CHAT_ORDERS, false, 'user', (string) $this->owner->id);
        $settingService->set(SettingKey::NOTIFY_CHAT_ORDERS, false, 'user', (string) $this->manager->id);

        $lead = Lead::create([
            'site_id' => $this->site->id,
            'type' => LeadType::ONE_CLICK,
            'status' => LeadStatus::NEW,
            'name' => 'Alice',
            'phone' => '+375291112233',
        ]);

        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->never())->method('sendMessage');

        $service = new TelegramService($botMock);
        $service->notifyManagersAboutNewLead($lead);
    }

    public function test_lead_notification_skipped_when_notify_chat_leads_is_false(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::NOTIFY_CHAT_LEADS, false, 'user', (string) $this->owner->id);
        $settingService->set(SettingKey::NOTIFY_CHAT_LEADS, false, 'user', (string) $this->manager->id);

        $lead = Lead::create([
            'site_id' => $this->site->id,
            'type' => LeadType::ONE_CLICK,
            'status' => LeadStatus::NEW,
            'name' => 'Alice',
            'phone' => '+375291112233',
        ]);

        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->never())->method('sendMessage');

        $service = new TelegramService($botMock);
        $service->notifyManagersAboutNewLead($lead);
    }
}
