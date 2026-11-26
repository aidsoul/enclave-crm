<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessIncomingOrderJob;
use App\Models\Customer;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\ActionLogger;
use App\Services\Orders\CustomerResolver;
use App\Services\Orders\OrderCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected OrderCreator $orderCreator;

    protected Site $site;

    protected User $manager;

    protected User $courier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderCreator = new OrderCreator(new ActionLogger, new CustomerResolver);

        $this->site = Site::create([
            'name' => 'Store 1',
            'domain' => 'store1.example.com',
            'token' => 'store1_token',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Alex',
            'telegram_user_id' => 30001,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Bob',
            'telegram_user_id' => 30002,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);
    }

    public function test_creates_order_with_items_and_calculated_prices(): void
    {
        $order = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Мария',
                'client_phone' => '+7 (999) 777-88-99',
                'client_address' => 'ул. Мира 5',
                'order_items' => [
                    ['name' => 'Товар 1', 'qty' => 2, 'price' => 500],
                    ['name' => 'Товар 2', 'qty' => 1, 'price' => 1000],
                ],
                'delivery_type' => DeliveryType::COURIER->value,
                'delivery_courier_id' => $this->courier->id,
                'payment_method' => PaymentMethod::CASH->value,
            ],
            $this->manager->id
        );

        $this->assertNotNull($order->id);
        $this->assertEquals(OrderStatus::CREATED, $order->status);
        $this->assertEquals(2000.00, (float) $order->total_price);
        $this->assertEquals(300.00, (float) $order->purchase_price); // 15% default
        $this->assertEquals($this->manager->id, $order->manager_id);

        $this->assertCount(2, $order->items);
        $this->assertEquals(75.00, (float) $order->items[0]->purchase_price);
        $this->assertEquals(150.00, (float) $order->items[1]->purchase_price);
        $this->assertNotNull($order->customer_id);

        $customer = Customer::find($order->customer_id);
        $this->assertEquals('Мария', $customer->name);
        $this->assertEquals('79997778899', $customer->phone);

        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'user_id' => $this->courier->id,
            'delivery_address' => 'ул. Мира 5',
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => PaymentMethod::CASH->value,
            'status' => PaymentStatus::PENDING->value,
            'amount' => 2000.00,
        ]);
    }

    public function test_post_orders_api_endpoint_success(): void
    {
        $token = Str::random(64);
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $this->manager->id,
            'telegram_user_id' => $this->manager->telegram_user_id,
            'role' => $this->manager->role->value,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', [
                'site_id' => $this->site->id,
                'client_name' => 'Дмитрий',
                'client_phone' => '+79990001122',
                'client_address' => 'г. Москва, ул. Тверская 1',
                'total_price' => 3500.50,
                'purchase_price' => 2000.00,
                'order_items' => [
                    ['name' => 'Кроссовки', 'qty' => 1, 'price' => 3500.50],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('order.total_price', '3500.50');
        $response->assertJsonPath('order.status', 'created');
    }

    public function test_post_orders_api_validation_error(): void
    {
        $token = Str::random(64);
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $this->manager->id,
            'telegram_user_id' => $this->manager->telegram_user_id,
            'role' => $this->manager->role->value,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', [
                'site_id' => 999999, // non existent
                'client_phone' => 'invalid-phone',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_id', 'client_phone']);
    }

    public function test_post_orders_api_phone_optional(): void
    {
        $token = Str::random(64);
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $this->manager->id,
            'telegram_user_id' => $this->manager->telegram_user_id,
            'role' => $this->manager->role->value,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', [
                'site_id' => $this->site->id,
                'client_name' => 'Иван',
                'order_items' => [
                    ['name' => 'Товар', 'qty' => 1, 'price' => 100],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_post_orders_api_phone_max_length_validation(): void
    {
        $token = Str::random(64);
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $this->manager->id,
            'telegram_user_id' => $this->manager->telegram_user_id,
            'role' => $this->manager->role->value,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', [
                'site_id' => $this->site->id,
                'client_phone' => '+123456789012345678901', // 22 chars
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_phone']);
    }

    public function test_post_orders_api_accepts_phone_alias(): void
    {
        $token = Str::random(64);
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $this->manager->id,
            'telegram_user_id' => $this->manager->telegram_user_id,
            'role' => $this->manager->role->value,
        ]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', [
                'site_id' => $this->site->id,
                'client_name' => 'Алексей',
                'phone' => '+7 (999) 111-22-33',
                'order_items' => [
                    ['name' => 'Товар', 'qty' => 1, 'price' => 100],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_incoming_orders_api_phone_validation(): void
    {
        // Missing phone is allowed (optional)
        $resMissing = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_name' => 'Петр',
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 100]],
            ]);
        $resMissing->assertStatus(202);

        // Invalid format
        $resInvalid = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_phone' => 'abc-invalid',
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 100]],
            ]);
        $resInvalid->assertStatus(422);
        $resInvalid->assertJsonValidationErrors(['client_phone']);
        $this->assertEquals('Некорректный формат телефона. Пример: +375 29 123-45-67.', $resInvalid->json('errors.client_phone.0'));

        // Too long
        $resTooLong = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_phone' => '+123456789012345678901',
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 100]],
            ]);
        $resTooLong->assertStatus(422);
        $resTooLong->assertJsonValidationErrors(['client_phone']);

        // Accepts phone alias
        $resAlias = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'phone' => '+375 29 111-22-33',
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 100]],
            ]);
        $resAlias->assertStatus(202);

        // Accepts client_data.phone
        $resClientData = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_data' => ['phone' => '+375 29 222-33-44'],
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 100]],
            ]);
        $resClientData->assertStatus(202);
    }

    public function test_order_creator_saves_external_id(): void
    {
        $order = $this->orderCreator->create(
            $this->site->id,
            [
                'external_id' => 'EXT-12345',
                'client_name' => 'Сергей',
                'client_phone' => '+375291234567',
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 150]],
            ]
        );

        $this->assertEquals('EXT-12345', $order->external_id);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'site_id' => $this->site->id,
            'external_id' => 'EXT-12345',
        ]);
    }

    public function test_incoming_orders_api_external_id_validation_and_deduplication(): void
    {
        // Accepts numeric external_id and converts it to string
        $res1 = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'external_id' => 888999,
                'client_name' => 'Анна',
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 200]],
            ]);
        $res1->assertStatus(202);

        // Accepts external_order_id alias
        $resAlias = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'external_order_id' => 'EXT-ALIAS-1',
                'client_name' => 'Ольга',
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 250]],
            ]);
        $resAlias->assertStatus(202);

        // Validation error if external_id exceeds 255 chars
        $resTooLong = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'external_id' => str_repeat('a', 256),
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 100]],
            ]);
        $resTooLong->assertStatus(422);
        $resTooLong->assertJsonValidationErrors(['external_id']);

        // Duplicate external_id returns 202 duplicate
        // Create an existing order in database with this site and external_id
        $this->orderCreator->create(
            $this->site->id,
            [
                'external_id' => 'UNIQUE-EXT-ID-1',
                'client_name' => 'Игорь',
                'order_items' => [['name' => 'Товар', 'qty' => 1, 'price' => 300]],
            ]
        );

        $resDup = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'external_id' => 'UNIQUE-EXT-ID-1',
                'client_name' => 'Другой клиент',
                'order_items' => [['name' => 'Товар', 'qty' => 2, 'price' => 300]],
            ]);
        $resDup->assertStatus(202);
        $resDup->assertJsonPath('duplicate', true);
    }

    public function test_post_orders_api_with_external_id_and_search(): void
    {
        $token = Str::random(64);
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $this->manager->id,
            'telegram_user_id' => $this->manager->telegram_user_id,
            'role' => $this->manager->role->value,
        ]));

        $createRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', [
                'site_id' => $this->site->id,
                'external_id' => 'SITE-ORDER-777',
                'client_name' => 'Виктор',
                'order_items' => [['name' => 'Телевизор', 'qty' => 1, 'price' => 1200]],
            ]);

        $createRes->assertStatus(201);
        $createRes->assertJsonPath('order.external_id', 'SITE-ORDER-777');

        // Search by external_id
        $searchRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/orders?search=SITE-ORDER-777');

        $searchRes->assertStatus(200);
        $this->assertNotEmpty($searchRes->json('orders'));
        $this->assertEquals('SITE-ORDER-777', $searchRes->json('orders.0.external_id'));
    }

    public function test_incoming_order_with_sku_city_and_client_comment(): void
    {
        $payload = [
            'external_id' => 'EXT-SKU-TEST',
            'client_name' => 'Евгений',
            'client_phone' => '+375 29 111-22-33',
            'client_comment' => 'Позвонить перед доставкой',
            'delivery_city' => 'Гомель',
            'delivery_address' => 'ул. Советская, д. 10',
            'order_items' => [
                [
                    'name' => 'Смартфон Xiaomi',
                    'sku' => 'MI-13T-256-BLK',
                    'qty' => 1,
                    'price' => 1400.00,
                ],
            ],
        ];

        $res = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', $payload);

        $res->assertStatus(202);

        ProcessIncomingOrderJob::dispatchSync($this->site->id, $payload);

        $this->assertDatabaseHas('order_items', [
            'name' => 'Смартфон Xiaomi',
            'sku' => 'MI-13T-256-BLK',
        ]);

        $this->assertDatabaseHas('deliveries', [
            'city' => 'Гомель',
            'delivery_address' => 'ул. Советская, д. 10',
        ]);

        $this->assertDatabaseHas('order_comments', [
            'body' => 'Позвонить перед доставкой',
            'author_type' => 'client',
            'user_id' => null,
        ]);
    }

    public function test_order_creation_uses_site_default_purchase_percent(): void
    {
        $siteWith30Percent = Site::create([
            'name' => 'Custom 30% Store',
            'domain' => 'store30.example.com',
            'default_purchase_percent' => 30.00,
            'is_active' => true,
        ]);

        $order = $this->orderCreator->create(
            $siteWith30Percent->id,
            [
                'client_name' => 'Сергей',
                'client_phone' => '+375291234567',
                'order_items' => [
                    ['name' => 'Товар А', 'qty' => 1, 'price' => 100],
                    ['name' => 'Товар Б', 'qty' => 2, 'price' => 200],
                ],
            ]
        );

        $this->assertEquals(500.00, (float) $order->total_price);
        // 30% of 100 + 30% of 400 = 30 + 120 = 150
        $this->assertEquals(150.00, (float) $order->purchase_price);
        $this->assertEquals(30.00, (float) $order->items[0]->purchase_price);
        $this->assertEquals(60.00, (float) $order->items[1]->purchase_price);
    }

    public function test_order_creation_uses_item_purchase_price_when_provided(): void
    {
        $order = $this->orderCreator->create(
            $this->site->id,
            [
                'client_name' => 'Ольга',
                'client_phone' => '+375297654321',
                'order_items' => [
                    ['name' => 'Товар 1', 'qty' => 2, 'price' => 100, 'purchase_price' => 40.00],
                    ['name' => 'Товар 2', 'qty' => 1, 'price' => 200], // fallback 15% -> 30.00
                ],
            ]
        );

        $this->assertEquals(400.00, (float) $order->total_price);
        // (40 * 2) + (30 * 1) = 80 + 30 = 110.00
        $this->assertEquals(110.00, (float) $order->purchase_price);
        $this->assertEquals(40.00, (float) $order->items[0]->purchase_price);
        $this->assertEquals(30.00, (float) $order->items[1]->purchase_price);
    }

    public function test_order_creation_uses_config_default_purchase_percent_when_site_has_none(): void
    {
        config(['orders.default_purchase_percent' => 25.0]);

        $siteWithoutPercent = Site::create([
            'name' => 'Store No Percent',
            'domain' => 'nopercent.example.com',
            'default_purchase_percent' => null,
            'is_active' => true,
        ]);

        $order = $this->orderCreator->create(
            $siteWithoutPercent->id,
            [
                'client_name' => 'Павел',
                'client_phone' => '+375298888888',
                'order_items' => [
                    ['name' => 'Товар В', 'qty' => 1, 'price' => 200],
                ],
            ]
        );

        $this->assertEquals(50.00, (float) $order->purchase_price);
        $this->assertEquals(50.00, (float) $order->items[0]->purchase_price);
    }

    public function test_post_orders_api_with_existing_customer_id(): void
    {
        $token = Str::random(64);
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $this->manager->id,
            'telegram_user_id' => $this->manager->telegram_user_id,
            'role' => $this->manager->role->value,
        ]));

        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Существующий Клиент',
            'phone' => '+375 29 123-45-67',
            'email' => 'existing@example.com',
            'address' => 'г. Минск, пр. Победителей 5',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', [
                'site_id' => $this->site->id,
                'customer_id' => $customer->id,
                'client_name' => $customer->name,
                'client_phone' => $customer->phone,
                'client_address' => $customer->address,
                'order_items' => [
                    ['name' => 'Товар X', 'qty' => 1, 'price' => 500],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('order.customer.id', $customer->id);
        $response->assertJsonPath('order.customer.name', 'Существующий Клиент');
    }
}
