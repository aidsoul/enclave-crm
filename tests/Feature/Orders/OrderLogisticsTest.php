<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessIncomingOrderJob;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\ActionLogger;
use App\Services\Orders\CustomerResolver;
use App\Services\Orders\OrderCreator;
use App\Services\Orders\OrderStatusMachine;
use App\Services\Orders\OrderUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderLogisticsTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected User $manager;

    protected User $courier;

    protected OrderCreator $orderCreator;

    protected OrderUpdater $orderUpdater;

    protected OrderStatusMachine $statusMachine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderCreator = new OrderCreator(new ActionLogger, new CustomerResolver);
        $this->orderUpdater = new OrderUpdater(new ActionLogger, new CustomerResolver);
        $this->statusMachine = new OrderStatusMachine(new ActionLogger);

        $this->site = Site::create([
            'name' => 'Demo Shop',
            'domain' => 'demo.example.com',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager John',
            'telegram_user_id' => 40001,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'Owner Alice',
            'telegram_user_id' => 40003,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Mike',
            'telegram_user_id' => 40002,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        Redis::flushdb();
        Cache::flush();
    }

    protected function authenticateTma(User $user): string
    {
        $token = Str::random(64);
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $user->id,
            'telegram_user_id' => $user->telegram_user_id,
            'role' => $user->role->value,
        ]));

        return $token;
    }

    public function test_incoming_order_deduplication(): void
    {
        Queue::fake();

        $payload = [
            'client_name' => 'Петр Петров',
            'client_phone' => '+375 29 111-22-33',
            'delivery_address' => 'г. Минск, ул. Сурганова 10',
            'order_items' => [
                ['name' => 'Товар A', 'qty' => 1, 'price' => 50],
            ],
        ];

        // First submission -> queued
        $res1 = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', $payload);

        $res1->assertStatus(202);
        $this->assertArrayNotHasKey('duplicate', $res1->json());
        Queue::assertPushed(ProcessIncomingOrderJob::class, 1);

        // Immediate second submission with same phone & site -> deduplicated
        $res2 = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', $payload);

        $res2->assertStatus(202);
        $res2->assertJsonPath('duplicate', true);
        Queue::assertPushed(ProcessIncomingOrderJob::class, 1); // still only 1 pushed

        // Submission with different phone -> queued
        $payload2 = $payload;
        $payload2['client_phone'] = '+375 29 999-88-77';

        $res3 = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', $payload2);

        $res3->assertStatus(202);
        $this->assertArrayNotHasKey('duplicate', $res3->json());
        Queue::assertPushed(ProcessIncomingOrderJob::class, 2);

        // Submission with same phone but different price -> queued (new order)
        $payloadDiffPrice = $payload;
        $payloadDiffPrice['order_items'] = [
            ['name' => 'Товар B', 'qty' => 1, 'price' => 100],
        ];

        $resDiffPrice = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', $payloadDiffPrice);

        $resDiffPrice->assertStatus(202);
        $this->assertArrayNotHasKey('duplicate', $resDiffPrice->json());
        Queue::assertPushed(ProcessIncomingOrderJob::class, 3);

        // When order was already taken into work (status != CREATED), identical order passes
        $acceptedOrder = $this->orderCreator->create($this->site->id, $payload);
        $acceptedOrder->update(['status' => OrderStatus::ACCEPTED]);
        Cache::flush();

        $resAccepted = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', $payload);

        $resAccepted->assertStatus(202);
        $this->assertArrayNotHasKey('duplicate', $resAccepted->json());
        Queue::assertPushed(ProcessIncomingOrderJob::class, 4);

        $completedOrder = $this->orderCreator->create($this->site->id, $payload);
        app(OrderStatusMachine::class)->changeStatus($completedOrder->id, OrderStatus::COMPLETED, $this->manager->id);

        $resCompleted = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', $payload);

        $resCompleted->assertStatus(202);
        $this->assertArrayNotHasKey('duplicate', $resCompleted->json());
        Queue::assertPushed(ProcessIncomingOrderJob::class, 5);
    }

    public function test_delivery_date_and_slot_persistence(): void
    {
        $order = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Ольга',
                'client_phone' => '+7 (903) 123-45-67',
                'delivery_date' => '2026-10-25',
                'delivery_slot' => '10:00–14:00',
                'delivery_type' => DeliveryType::COURIER->value,
                'order_items' => [
                    ['name' => 'Книга', 'qty' => 1, 'price' => 1500],
                ],
            ],
            $this->manager->id
        );

        $delivery = $order->delivery()->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('2026-10-25', $delivery->delivery_date?->format('Y-m-d'));
        $this->assertEquals('10:00–14:00', $delivery->delivery_slot);

        // Update via OrderUpdater
        $updatedOrder = $this->orderUpdater->update(
            $order->id,
            [
                'delivery_date' => '2026-10-26',
                'delivery_slot' => '14:00–18:00',
            ],
            $this->manager->id
        );

        $updatedDelivery = $updatedOrder->delivery()->first();
        $this->assertEquals('2026-10-26', $updatedDelivery->delivery_date?->format('Y-m-d'));
        $this->assertEquals('14:00–18:00', $updatedDelivery->delivery_slot);
    }

    public function test_auto_close_payment_on_order_completed(): void
    {
        $order = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Сергей',
                'client_phone' => '+7 (916) 555-44-33',
                'payment_method' => PaymentMethod::CASH->value,
                'payment_status' => PaymentStatus::PENDING->value,
                'order_items' => [
                    ['name' => 'Рюкзак', 'qty' => 1, 'price' => 4000],
                ],
            ],
            $this->manager->id
        );

        $payment = $order->payment()->first();
        $this->assertEquals(PaymentStatus::PENDING, $payment->status);

        // Advance to at_warehouse then complete
        $order->update(['status' => OrderStatus::AT_WAREHOUSE]);

        $this->statusMachine->markComplete($order->id, $this->manager->id);

        $freshPayment = $payment->fresh();
        $this->assertEquals(PaymentStatus::PAID, $freshPayment->status);
    }

    public function test_bulk_assign_courier_endpoint(): void
    {
        $order1 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент 1',
                'client_phone' => '+79001112233',
                'order_items' => [['name' => 'Товар 1', 'qty' => 1, 'price' => 100]],
            ],
            $this->manager->id
        );

        $order2 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент 2',
                'client_phone' => '+79002223344',
                'order_items' => [['name' => 'Товар 2', 'qty' => 1, 'price' => 200]],
            ],
            $this->manager->id
        );

        $token = $this->authenticateTma($this->manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders/bulk-assign', [
                'order_ids' => [$order1->id, $order2->id],
                'delivery_courier_id' => $this->courier->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 2);

        $this->assertEquals($this->courier->id, $order1->fresh()->delivery()->first()->user_id);
        $this->assertEquals($this->courier->id, $order2->fresh()->delivery()->first()->user_id);
        $this->assertEquals(OrderStatus::ASSIGNED, $order1->fresh()->status);
        $this->assertEquals(OrderStatus::ASSIGNED, $order2->fresh()->status);
    }

    public function test_bulk_at_warehouse_endpoint(): void
    {
        $order1 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент 3',
                'client_phone' => '+79003334455',
                'order_items' => [['name' => 'Товар 3', 'qty' => 1, 'price' => 300]],
            ],
            $this->manager->id
        );
        $order1->update(['status' => OrderStatus::ASSIGNED]);

        $order2 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент 4',
                'client_phone' => '+79004445566',
                'order_items' => [['name' => 'Товар 4', 'qty' => 1, 'price' => 400]],
            ],
            $this->manager->id
        );
        $order2->update(['status' => OrderStatus::ASSIGNED]);

        $token = $this->authenticateTma($this->manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders/bulk-at-warehouse', [
                'order_ids' => [$order1->id, $order2->id],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 2);

        $this->assertEquals(OrderStatus::AT_WAREHOUSE, $order1->fresh()->status);
        $this->assertEquals(OrderStatus::AT_WAREHOUSE, $order2->fresh()->status);
    }

    public function test_courier_cannot_call_bulk_endpoints(): void
    {
        $token = $this->authenticateTma($this->courier);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders/bulk-assign', [
                'order_ids' => [1],
                'delivery_courier_id' => $this->courier->id,
            ]);
        $response->assertStatus(403);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders/bulk-at-warehouse', [
                'order_ids' => [1],
            ]);
        $response->assertStatus(403);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders/bulk-accept', [
                'order_ids' => [1],
            ]);
        $response->assertStatus(403);
    }

    public function test_assign_courier_fails_when_order_has_no_manager(): void
    {
        $order = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент без менеджера',
                'client_phone' => '+79005556677',
                'order_items' => [['name' => 'Товар X', 'qty' => 1, 'price' => 500]],
            ]
        );
        $order->update(['manager_id' => null]);

        $token = $this->authenticateTma($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/orders/{$order->id}/assign", [
                'delivery_courier_id' => $this->courier->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Нельзя назначить курьера на заказ без назначенного менеджера');
    }

    public function test_bulk_accept_assigns_manager_to_orders(): void
    {
        $order1 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент A',
                'client_phone' => '+79006667788',
                'order_items' => [['name' => 'Товар A', 'qty' => 1, 'price' => 100]],
            ]
        );
        $order1->update(['manager_id' => null, 'status' => OrderStatus::CREATED]);

        $order2 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент B',
                'client_phone' => '+79007778899',
                'order_items' => [['name' => 'Товар B', 'qty' => 1, 'price' => 200]],
            ]
        );
        $order2->update(['manager_id' => null, 'status' => OrderStatus::CREATED]);

        $token = $this->authenticateTma($this->manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders/bulk-accept', [
                'order_ids' => [$order1->id, $order2->id],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 2);

        $this->assertEquals($this->manager->id, $order1->fresh()->manager_id);
        $this->assertEquals($this->manager->id, $order2->fresh()->manager_id);
        $this->assertEquals(OrderStatus::ACCEPTED, $order1->fresh()->status);
        $this->assertEquals(OrderStatus::ACCEPTED, $order2->fresh()->status);
    }

    public function test_owner_can_bulk_assign_manager(): void
    {
        $order1 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент C1',
                'client_phone' => '+79001110011',
                'order_items' => [['name' => 'Товар C1', 'qty' => 1, 'price' => 150]],
            ]
        );
        $order1->update(['manager_id' => null, 'status' => OrderStatus::CREATED]);

        $order2 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент C2',
                'client_phone' => '+79001110022',
                'order_items' => [['name' => 'Товар C2', 'qty' => 1, 'price' => 250]],
            ]
        );
        $order2->update(['manager_id' => null, 'status' => OrderStatus::CREATED]);

        $token = $this->authenticateTma($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders/bulk-assign-manager', [
                'order_ids' => [$order1->id, $order2->id],
                'manager_id' => $this->manager->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 2);

        $this->assertEquals($this->manager->id, $order1->fresh()->manager_id);
        $this->assertEquals($this->manager->id, $order2->fresh()->manager_id);
        $this->assertEquals(OrderStatus::ACCEPTED, $order1->fresh()->status);
        $this->assertEquals(OrderStatus::ACCEPTED, $order2->fresh()->status);
    }

    public function test_manager_and_courier_cannot_call_bulk_assign_manager(): void
    {
        $managerToken = $this->authenticateTma($this->manager);
        $responseManager = $this->withHeader('Authorization', "Bearer {$managerToken}")
            ->postJson('/api/orders/bulk-assign-manager', [
                'order_ids' => [1],
                'manager_id' => $this->manager->id,
            ]);
        $responseManager->assertStatus(403);

        $courierToken = $this->authenticateTma($this->courier);
        $responseCourier = $this->withHeader('Authorization', "Bearer {$courierToken}")
            ->postJson('/api/orders/bulk-assign-manager', [
                'order_ids' => [1],
                'manager_id' => $this->manager->id,
            ]);
        $responseCourier->assertStatus(403);
    }

    public function test_manager_can_cancel_own_order(): void
    {
        $order = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент Отмена',
                'client_phone' => '+79001112233',
                'order_items' => [['name' => 'Товар 1', 'qty' => 1, 'price' => 100]],
            ]
        );
        $order->update(['manager_id' => $this->manager->id, 'status' => OrderStatus::ACCEPTED]);

        $token = $this->authenticateTma($this->manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200);
        $this->assertEquals(OrderStatus::CANCELLED, $order->fresh()->status);
    }

    public function test_manager_cannot_cancel_other_managers_order(): void
    {
        $otherManager = User::create([
            'system_name' => 'Other Manager',
            'telegram_user_id' => 40099,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $order = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент Другого',
                'client_phone' => '+79001112244',
                'order_items' => [['name' => 'Товар 2', 'qty' => 1, 'price' => 150]],
            ]
        );
        $order->update(['manager_id' => $otherManager->id, 'status' => OrderStatus::ACCEPTED]);

        $token = $this->authenticateTma($this->manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(403);
        $this->assertEquals(OrderStatus::ACCEPTED, $order->fresh()->status);
    }

    public function test_manager_can_bulk_cancel_own_orders(): void
    {
        $order1 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент Отмена 1',
                'client_phone' => '+79001112255',
                'order_items' => [['name' => 'Товар 3', 'qty' => 1, 'price' => 100]],
            ]
        );
        $order1->update(['manager_id' => $this->manager->id, 'status' => OrderStatus::ACCEPTED]);

        $order2 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент Отмена 2',
                'client_phone' => '+79001112266',
                'order_items' => [['name' => 'Товар 4', 'qty' => 1, 'price' => 200]],
            ]
        );
        $order2->update(['manager_id' => $this->manager->id, 'status' => OrderStatus::ACCEPTED]);

        $token = $this->authenticateTma($this->manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders/bulk-cancel', [
                'order_ids' => [$order1->id, $order2->id],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 2);
        $this->assertEquals(OrderStatus::CANCELLED, $order1->fresh()->status);
        $this->assertEquals(OrderStatus::CANCELLED, $order2->fresh()->status);
    }

    public function test_manager_bulk_cancel_fails_for_unassigned_or_other_manager_orders(): void
    {
        $otherManager = User::create([
            'system_name' => 'Other Manager 2',
            'telegram_user_id' => 40098,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $order1 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент 1',
                'client_phone' => '+79001112277',
                'order_items' => [['name' => 'Товар 5', 'qty' => 1, 'price' => 100]],
            ]
        );
        $order1->update(['manager_id' => $this->manager->id, 'status' => OrderStatus::ACCEPTED]);

        $order2 = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент 2',
                'client_phone' => '+79001112288',
                'order_items' => [['name' => 'Товар 6', 'qty' => 1, 'price' => 200]],
            ]
        );
        $order2->update(['manager_id' => $otherManager->id, 'status' => OrderStatus::ACCEPTED]);

        $token = $this->authenticateTma($this->manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders/bulk-cancel', [
                'order_ids' => [$order1->id, $order2->id],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('count', 1);
        $this->assertEquals(OrderStatus::CANCELLED, $order1->fresh()->status);
        $this->assertEquals(OrderStatus::ACCEPTED, $order2->fresh()->status);
    }

    public function test_courier_cannot_see_purchase_price_in_order_resource(): void
    {
        $order = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Клиент Секрет',
                'client_phone' => '+79001112299',
                'purchase_price' => 150.00,
                'order_items' => [
                    ['name' => 'Товар Секрет', 'qty' => 1, 'price' => 300, 'purchase_price' => 150.00],
                ],
            ]
        );
        $order->delivery()->create([
            'user_id' => $this->courier->id,
            'type' => DeliveryType::COURIER,
        ]);

        $courierToken = $this->authenticateTma($this->courier);
        $courierRes = $this->withHeader('Authorization', "Bearer {$courierToken}")
            ->getJson("/api/orders/{$order->id}");
        $courierRes->assertOk();
        $this->assertNull($courierRes->json('order.purchase_price'));
        $this->assertNull($courierRes->json('order.items.0.purchase_price'));

        $managerToken = $this->authenticateTma($this->manager);
        $managerRes = $this->withHeader('Authorization', "Bearer {$managerToken}")
            ->getJson("/api/orders/{$order->id}");
        $managerRes->assertOk();
        $this->assertEquals(150.00, $managerRes->json('order.purchase_price'));
        $this->assertEquals(150.00, $managerRes->json('order.items.0.purchase_price'));
    }
}
