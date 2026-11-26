<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessIncomingOrderJob;
use App\Models\ActionLog;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderDeliveryPriceTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected User $manager;

    protected User $owner;

    protected string $managerToken;

    protected string $siteApiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteApiToken = (string) Str::uuid();

        $this->site = Site::create([
            'name' => 'Store Test',
            'domain' => 'test-delivery.example.com',
            'api_token' => $this->siteApiToken,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Alex',
            'telegram_user_id' => 9001,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 9002,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->managerToken = Str::random(64);
        Redis::setex("session:{$this->managerToken}", 3600, json_encode([
            'user_id' => $this->manager->id,
            'telegram_user_id' => $this->manager->telegram_user_id,
            'role' => $this->manager->role->value,
        ]));
    }

    public function test_create_order_with_delivery_price_adds_to_total_price_if_not_specified(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'client_name' => 'Иван Иванов',
            'client_phone' => '+375 29 111-22-33',
            'client_address' => 'г. Минск, ул. Ленина 1',
            'delivery_price' => 15.00,
            'order_items' => [
                [
                    'name' => 'Товар А',
                    'qty' => 2,
                    'price' => 50.00,
                    'purchase_price' => 20.00,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->managerToken}")
            ->postJson('/api/orders', $payload);

        $response->assertCreated();

        $orderId = $response->json('order.id');
        $order = Order::with('delivery')->find($orderId);

        // 2 * 50 = 100 + 15 delivery_price = 115 total_price
        $this->assertEquals(115.00, (float) $order->total_price);
        $this->assertEquals(15.00, (float) $order->delivery->first()->delivery_price);

        // Resource response check
        $showResponse = $this->withHeader('Authorization', "Bearer {$this->managerToken}")
            ->getJson("/api/orders/{$orderId}");
        $showResponse->assertOk()
            ->assertJsonPath('order.delivery.0.delivery_price', 15);
    }

    public function test_create_order_with_explicit_total_price_and_delivery_price(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'client_name' => 'Петр Петров',
            'client_phone' => '+375 29 222-33-44',
            'client_address' => 'г. Минск, пр. Победителей 5',
            'delivery_price' => 10.00,
            'total_price' => 150.00, // explicit total override
            'order_items' => [
                [
                    'name' => 'Товар Б',
                    'qty' => 1,
                    'price' => 140.00,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->managerToken}")
            ->postJson('/api/orders', $payload);

        $response->assertCreated();

        $orderId = $response->json('order.id');
        $order = Order::with('delivery')->find($orderId);

        $this->assertEquals(150.00, (float) $order->total_price);
        $this->assertEquals(10.00, (float) $order->delivery->first()->delivery_price);
    }

    public function test_update_order_delivery_price_updates_delivery_and_logs_audit(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 100.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);

        $order->delivery()->create([
            'type' => 'courier',
            'delivery_price' => 5.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->managerToken}")
            ->patchJson("/api/orders/{$order->id}", [
                'delivery_price' => 20.00,
            ]);

        $response->assertOk();

        $fresh = $order->fresh(['delivery']);
        $this->assertEquals(20.00, (float) $fresh->delivery->first()->delivery_price);
        // Total price adjusted by delta +15.00: 100 + 15 = 115.00
        $this->assertEquals(115.00, (float) $fresh->total_price);

        // Verify ActionLog audit entry
        $log = ActionLog::where('order_id', $order->id)
            ->where('field_name', 'delivery.price')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('5.00', $log->old_value);
        $this->assertEquals('20', $log->new_value);
    }

    public function test_incoming_order_with_delivery_price(): void
    {
        $payload = [
            'client_name' => 'Анна Сидорова',
            'client_phone' => '+375 29 333-44-55',
            'client_address' => 'г. Минск, ул. Коласа 10',
            'delivery_price' => 12.50,
            'order_items' => [
                [
                    'name' => 'Платье',
                    'qty' => 1,
                    'price' => 80.00,
                ],
            ],
        ];

        $response = $this->withHeader('X-API-Token', $this->siteApiToken)
            ->postJson('/api/orders/incoming', $payload);

        $response->assertStatus(202);

        ProcessIncomingOrderJob::dispatchSync($this->site->id, $payload);

        $order = Order::where('site_id', $this->site->id)->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertEquals(12.50, (float) $order->delivery->first()->delivery_price);
        $this->assertEquals(92.50, (float) $order->total_price); // 80 + 12.50
    }
}
