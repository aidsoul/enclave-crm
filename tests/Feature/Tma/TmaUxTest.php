<?php

namespace Tests\Feature\Tma;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Telegram\Handlers\OrderInlineQuery;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\UserSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Inline\InlineQuery;
use Tests\TestCase;

class TmaUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $courier;

    protected User $manager;

    protected Site $site;

    protected Customer $customer;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 88000,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);
        $this->owner->setCloudPassword('owner_password_123');

        $this->courier = User::create([
            'system_name' => 'Courier Pavel',
            'telegram_user_id' => 88001,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);
        $this->courier->setCloudPassword('courier_password_123');

        $this->manager = User::create([
            'system_name' => 'Manager Olga',
            'telegram_user_id' => 88002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);
        $this->manager->setCloudPassword('manager_password_123');

        $this->site = Site::create([
            'name' => 'Test Store',
            'domain' => 'store.local',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Ivan Client',
            'phone' => '+375291234567',
            'address' => 'г. Минск, пр-т Победителей 50',
        ]);

        $this->order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-UX-01',
            'total_price' => 120,
            'purchase_price' => 70,
            'status' => OrderStatus::ASSIGNED,
            'manager_id' => $this->manager->id,
        ]);

        Delivery::create([
            'order_id' => $this->order->id,
            'courier_id' => $this->courier->id,
            'type' => DeliveryType::COURIER,
            'delivery_address' => 'г. Минск, пр-т Победителей 50',
        ]);
    }

    public function test_courier_page_contains_navigator_actions(): void
    {
        $response = $this->actingAs($this->courier)->get('/tma/courier');
        $response->assertOk();
        $response->assertSee('openNavigator(\'yandex\'', false);
        $response->assertSee('openNavigator(\'2gis\'', false);
        $response->assertSee('openNavigator(\'google\'', false);
        $response->assertSee('Яндекс');
        $response->assertSee('2GIS');
        $response->assertSee('Google');
        $response->assertSee('Маршрутный лист');
        $response->assertSee('toggleRouteSort()', false);
        $response->assertSee('По окнам доставки');
        $response->assertSee('displayOrders', false);
    }

    public function test_order_show_page_contains_navigator_actions(): void
    {
        $response = $this->actingAs($this->courier)->get("/tma/orders/{$this->order->id}");
        $response->assertOk();
        $response->assertSee('openNavigator(\'yandex\'', false);
        $response->assertSee('openNavigator(\'2gis\'', false);
        $response->assertSee('openNavigator(\'google\'', false);
    }

    public function test_layout_contains_haptic_feedback_notification_support(): void
    {
        $response = $this->actingAs($this->courier)->get('/tma/courier');
        $response->assertOk();
        $response->assertSee('Telegram?.WebApp?.HapticFeedback', false);
    }

    public function test_owner_dashboard_is_active_in_navigation_on_default_load(): void
    {
        $response = $this->actingAs($this->owner)->get('/');
        $response->assertOk();
        $response->assertSee('<a href="/tma" class="flex-1 flex flex-col items-center gap-0.5 py-1 text-primary">', false);
        $response->assertSee('<a href="/tma/orders" class="flex-1 flex flex-col items-center gap-0.5 py-1 text-fg-subtle">', false);
    }

    public function test_owner_dashboard_is_active_when_accessing_tma_path(): void
    {
        $response = $this->actingAs($this->owner)->get('/tma');
        $response->assertOk();
        $response->assertSee('<a href="/tma" class="flex-1 flex flex-col items-center gap-0.5 py-1 text-primary">', false);
        $response->assertSee('<a href="/tma/orders" class="flex-1 flex flex-col items-center gap-0.5 py-1 text-fg-subtle">', false);
    }

    public function test_owner_dashboard_has_all_orders_button(): void
    {
        $response = $this->actingAs($this->owner)->get('/');
        $response->assertOk();
        $response->assertSee('href="/tma/orders"', false);
        $response->assertSee('Все заказы');
    }

    public function test_order_show_page_contains_share_order_button(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::CREATED,
            'purchase_price' => 50.00,
            'total_price' => 120.00,
            'delivery_address' => 'ул. Ленина 10',
        ]);

        $response = $this->actingAs($this->manager)->get('/tma/orders/'.$order->id);
        $response->assertOk();
        $response->assertSee('shareOrder()', false);
        $response->assertSee('title="Поделиться заказом"', false);
    }

    public function test_order_inline_query_returns_articles_for_authorized_user(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::CREATED,
            'purchase_price' => 70.00,
            'total_price' => 150.00,
            'delivery_address' => 'ул. Пушкина 15',
        ]);

        $bot = $this->createMock(Nutgram::class);
        $inlineQuery = new InlineQuery;
        $inlineQuery->query = 'order:'.$order->id;

        $tgUser = new \SergiX44\Nutgram\Telegram\Types\User\User;
        $tgUser->id = 88002;

        $bot->method('inlineQuery')->willReturn($inlineQuery);
        $bot->method('user')->willReturn($tgUser);

        $bot->expects($this->once())
            ->method('answerInlineQuery')
            ->with(
                $this->callback(function (array $results) use ($order) {
                    if (count($results) !== 1) {
                        return false;
                    }
                    $article = $results[0];
                    $hasTitle = str_contains($article->title, "Заказ #{$order->id}");
                    $hasStartAppInText = str_contains($article->input_message_content->message_text, "startapp=order_{$order->id}");
                    $buttonUrl = $article->reply_markup->inline_keyboard[0][0]->url ?? '';
                    $hasStartAppInButton = str_contains($buttonUrl, "startapp=order_{$order->id}");

                    return $hasTitle && $hasStartAppInText && $hasStartAppInButton;
                }),
                $this->anything()
            );

        $handler = new OrderInlineQuery;
        $handler($bot);
    }

    public function test_order_inline_query_returns_empty_for_unauthorized_user(): void
    {
        $bot = $this->createMock(Nutgram::class);
        $inlineQuery = new InlineQuery;
        $inlineQuery->query = 'order:999';

        $tgUser = new \SergiX44\Nutgram\Telegram\Types\User\User;
        $tgUser->id = 99999999;

        $bot->method('inlineQuery')->willReturn($inlineQuery);
        $bot->method('user')->willReturn($tgUser);

        $bot->expects($this->once())
            ->method('answerInlineQuery')
            ->with($this->equalTo([]), $this->anything());

        $handler = new OrderInlineQuery;
        $handler($bot);
    }

    public function test_manager_navigation_items(): void
    {
        $response = $this->actingAs($this->manager)->get('/tma/manager');
        $response->assertOk();
        $response->assertSee('href="/tma/manager"', false);
        $response->assertSee('Профиль');
        $response->assertSee('href="/tma/orders"', false);
        $response->assertSee('Заказы');
        $response->assertSee('href="/tma/leads"', false);
        $response->assertSee('Заявки');
        $response->assertSee('href="/tma/customers"', false);
        $response->assertSee('Клиенты');
        $response->assertSee('href="/tma/settings"', false);
        $response->assertSee('Настройки');
        $response->assertDontSee('>Создать<', false);
    }

    public function test_owner_accessing_manager_page_receives_manager_view(): void
    {
        $response = $this->actingAs($this->owner)->get('/tma/manager');
        $response->assertOk();
        $response->assertViewIs('tma.manager.orders');
        $response->assertDontSee('stats.total_revenue', false);
    }

    public function test_manager_accessing_root_receives_manager_view(): void
    {
        $response = $this->actingAs($this->manager)->get('/');
        $response->assertOk();
        $response->assertViewIs('tma.manager.orders');
        $response->assertDontSee('stats.total_revenue', false);
    }

    public function test_courier_accessing_root_receives_courier_view(): void
    {
        $response = $this->actingAs($this->courier)->get('/');
        $response->assertOk();
        $response->assertViewIs('tma.courier.index');
    }

    public function test_unauthenticated_guest_blocked_from_protected_tma_views(): void
    {
        $this->get('/tma/financial')->assertForbidden();
        $this->get('/tma/personnel')->assertForbidden();
        $this->get('/tma/sites')->assertForbidden();
        $this->get('/tma/audit')->assertForbidden();
        $this->get('/tma/orders')->assertForbidden();
        $this->get('/tma/customers')->assertForbidden();
        $this->get('/tma/leads')->assertForbidden();
        $this->get('/tma/settings')->assertForbidden();
        $this->get('/tma/analytics')->assertForbidden();
        $this->get('/tma/new')->assertForbidden();
    }

    public function test_unauthenticated_guest_receives_entry_shell_without_internal_markup(): void
    {
        foreach (['/', '/tma', '/tma/manager', '/tma/courier'] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertViewIs('tma.auth.entry');
            $response->assertDontSee('stats.total_revenue', false);
            $response->assertDontSee('Общий оборот', false);
            $response->assertDontSee('Чистая прибыль', false);
            $response->assertDontSee('href="/tma/financial"', false);
            $response->assertDontSee('href="/tma/personnel"', false);
            $response->assertSee('window.location.replace(\'/blocked\')', false);
            $response->assertSee('/api/invitations/accept', false);
        }
    }

    public function test_blocked_route_returns_403_forbidden(): void
    {
        $response = $this->get('/blocked');
        $response->assertForbidden();
        $response->assertViewIs('tma.blocked');
        $response->assertSee('Доступ ограничен');
    }

    public function test_user_without_cloud_password_is_forced_to_setup_page(): void
    {
        $userWithoutPwd = User::create([
            'system_name' => 'User Without Password',
            'telegram_user_id' => 777111222,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($userWithoutPwd, $token, (int) $userWithoutPwd->telegram_user_id, 3600, '127.0.0.1');

        $response = $this->withSession(['tma_user_id' => $token])->get('/tma/manager');
        $response->assertForbidden();
        $response->assertViewIs('tma.auth.verify-ip');
        $response->assertSee('Задайте облачный пароль');
        $response->assertSee('Для защиты вашего аккаунта установите персональный облачный пароль:');
    }

    public function test_document_request_authenticates_via_query_token_parameter(): void
    {
        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($this->owner, $token, (int) $this->owner->telegram_user_id, 3600, '127.0.0.1');

        $response = $this->get("/tma/owner?token={$token}");
        $response->assertOk();
        $response->assertViewIs('tma.owner.dashboard');
        $this->assertEquals($this->owner->id, auth()->id());
    }
}
