<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Events\OrderAssigned;
use App\Events\OrderStatusChanged;
use App\Jobs\NotifyCourierJob;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\OrderAssigner;
use App\Services\Orders\OrderUpdater;
use App\Services\SettingService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class CourierAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected User $manager;

    protected User $courier;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'name' => 'Test Site',
            'domain' => 'test.local',
            'token' => 'test_token',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Alex',
            'telegram_user_id' => 10001,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Bob',
            'telegram_user_id' => 20002,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 150.00,
            'purchase_price' => 100.00,
            'status' => OrderStatus::ACCEPTED,
        ]);
    }

    public function test_assign_courier_dispatches_events_and_notification_job(): void
    {
        Event::fake([OrderAssigned::class, OrderStatusChanged::class]);
        Queue::fake([NotifyCourierJob::class]);

        $assigner = app(OrderAssigner::class);
        $order = $assigner->assignCourier($this->order->id, $this->courier->id, $this->manager->id);

        $this->assertEquals(OrderStatus::ASSIGNED, $order->status);
        $this->assertEquals($this->courier->id, $order->delivery()->first()->user_id);

        Event::assertDispatched(OrderAssigned::class, function (OrderAssigned $event) {
            $payload = $event->broadcastWith();

            return $event->order->id === $this->order->id
                && (string) $event->courierTelegramUserId === (string) $this->courier->telegram_user_id
                && $payload['event'] === 'OrderAssigned'
                && $payload['order_id'] === $this->order->id
                && isset($payload['order']['id'])
                && $payload['order']['id'] === $this->order->id;
        });

        Event::assertDispatched(OrderStatusChanged::class);
        Queue::assertPushed(NotifyCourierJob::class);
    }

    public function test_order_updater_dispatches_assignment_when_delivery_courier_is_set(): void
    {
        Event::fake([OrderAssigned::class, OrderStatusChanged::class]);
        Queue::fake([NotifyCourierJob::class]);

        $updater = app(OrderUpdater::class);
        $updated = $updater->update($this->order->id, [
            'delivery_courier_id' => $this->courier->id,
            'delivery_address' => 'ул. Ленина 10',
        ], $this->manager->id);

        $this->assertEquals(OrderStatus::ASSIGNED, $updated->status);
        $this->assertEquals($this->courier->id, $updated->delivery()->first()->user_id);

        Event::assertDispatched(OrderAssigned::class, function (OrderAssigned $event) {
            return $event->order->id === $this->order->id
                && (string) $event->courierTelegramUserId === (string) $this->courier->telegram_user_id;
        });

        Event::assertDispatched(OrderStatusChanged::class);
        Queue::assertPushed(NotifyCourierJob::class);
    }

    public function test_telegram_service_formats_and_sends_notification_to_courier(): void
    {
        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->once())
            ->method('sendMessage')
            ->with(
                $this->callback(function ($text) {
                    $currency = config('app.currency_symbol', 'Br');

                    return str_contains($text, "Вам назначен заказ #{$this->order->id}")
                        && str_contains($text, "150.00 {$currency}");
                }),
                (string) $this->courier->telegram_user_id
            );

        $service = new TelegramService($botMock);
        $service->notifyCourierAboutAssignment($this->order, $this->courier);
    }

    public function test_telegram_service_skips_notification_when_chat_orders_disabled(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::NOTIFY_CHAT_ORDERS, false, 'user', (string) $this->courier->id);

        $botMock = $this->createMock(Nutgram::class);
        $botMock->expects($this->never())->method('sendMessage');

        $service = new TelegramService($botMock);
        $service->notifyCourierAboutAssignment($this->order, $this->courier);
    }
}
