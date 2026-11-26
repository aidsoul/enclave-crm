<?php

namespace Tests\Feature\Orders;

use App\Enums\ActionType;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValuableShipmentOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected User $manager;

    protected User $owner;

    protected User $courier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'name' => 'Store 1',
            'domain' => 'store1.example.com',
            'token' => 'store1_token',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 10001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Alex',
            'telegram_user_id' => 10002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Bob',
            'telegram_user_id' => 10003,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);
    }

    public function test_manager_can_create_order_marked_as_valuable(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'client_name' => 'Дмитрий',
            'client_phone' => '+375 29 123-45-67',
            'client_address' => 'г. Минск, пр-т Победителей 10',
            'order_items' => [
                ['name' => 'Золотые часы', 'qty' => 1, 'price' => 500, 'purchase_price' => 200],
            ],
            'total_price' => 500,
            'purchase_price' => 200,
            'is_valuable' => true,
            'delivery_type' => DeliveryType::COURIER->value,
            'payment_method' => PaymentMethod::CASH->value,
        ];

        $response = $this->actingAs($this->manager)->postJson('/api/orders', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('order.is_valuable', true);
        $response->assertJsonPath('order.requires_signature', true);

        $order = Order::find($response->json('order.id'));
        $this->assertNotNull($order);
        $this->assertTrue($order->is_valuable);
        $this->assertTrue($order->requiresClientSignature());
    }

    public function test_manager_can_create_order_with_valuable_false_by_default(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'client_name' => 'Ольга',
            'client_phone' => '+375 29 987-65-43',
            'client_address' => 'г. Минск, ул. Сурганова 5',
            'order_items' => [
                ['name' => 'Чехол для телефона', 'qty' => 1, 'price' => 20, 'purchase_price' => 10],
            ],
            'total_price' => 20,
            'purchase_price' => 10,
            'is_valuable' => false,
        ];

        $response = $this->actingAs($this->manager)->postJson('/api/orders', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('order.is_valuable', false);
        $response->assertJsonPath('order.requires_signature', false);

        $order = Order::find($response->json('order.id'));
        $this->assertFalse($order->is_valuable);
        $this->assertFalse($order->requiresClientSignature());
    }

    public function test_manager_can_update_order_to_valuable(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 150.00,
            'purchase_price' => 80.00,
            'status' => OrderStatus::CREATED,
            'is_valuable' => false,
        ]);

        $response = $this->actingAs($this->manager)->patchJson("/api/orders/{$order->id}", [
            'is_valuable' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('order.is_valuable', true);
        $response->assertJsonPath('order.requires_signature', true);

        $order->refresh();
        $this->assertTrue($order->is_valuable);
        $this->assertTrue($order->requiresClientSignature());

        $this->assertDatabaseHas('action_logs', [
            'order_id' => $order->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'is_valuable',
            'old_value' => '0',
            'new_value' => '1',
        ]);
    }

    public function test_manager_can_unmark_valuable_order(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 150.00,
            'purchase_price' => 80.00,
            'status' => OrderStatus::CREATED,
            'is_valuable' => true,
        ]);

        $response = $this->actingAs($this->manager)->patchJson("/api/orders/{$order->id}", [
            'is_valuable' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('order.is_valuable', false);
        $response->assertJsonPath('order.requires_signature', false);

        $order->refresh();
        $this->assertFalse($order->is_valuable);

        $this->assertDatabaseHas('action_logs', [
            'order_id' => $order->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'is_valuable',
            'old_value' => '1',
            'new_value' => '0',
        ]);
    }

    public function test_valuable_order_blocks_completion_without_signature(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'courier_id' => $this->courier->id,
            'total_price' => 100.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::ASSIGNED,
            'is_valuable' => true,
        ]);

        $order->delivery()->create([
            'user_id' => $this->courier->id,
            'type' => DeliveryType::COURIER,
            'city' => 'Минск',
            'delivery_address' => 'ул. Ленина 1',
        ]);

        $response = $this->actingAs($this->courier)->patchJson("/api/orders/{$order->id}/complete");
        $response->assertStatus(422);
        $response->assertJsonFragment(['Для ценного отправления требуется подпись клиента']);
    }
}
