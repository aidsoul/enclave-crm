<?php

namespace Tests\Feature\Optimization;

use App\Dto\Order\CreateOrderDto;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessIncomingLeadJob;
use App\Jobs\ProcessIncomingOrderJob;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use App\Services\DataRetentionService;
use App\Services\Orders\CustomerResolver;
use App\Services\Orders\OrderAssigner;
use App\Services\Orders\OrderCreator;
use App\Services\Orders\OrderStatusMachine;
use App\Services\TelegramService;
use App\Services\UserSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RaceConditionAndSecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $courier;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'role' => UserRole::OWNER,
            'system_name' => 'Owner Test',
            'telegram_user_id' => '111111',
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'role' => UserRole::COURIER,
            'system_name' => 'Courier Test',
            'telegram_user_id' => '222222',
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Test Site',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);
    }

    public function test_consume_desktop_token_is_atomic_and_one_time(): void
    {
        $sessionService = app(UserSessionService::class);
        $token = $sessionService->createDesktopToken($this->owner->id);

        $firstConsume = $sessionService->consumeDesktopToken($token);
        $secondConsume = $sessionService->consumeDesktopToken($token);

        $this->assertEquals($this->owner->id, $firstConsume);
        $this->assertNull($secondConsume);
    }

    public function test_reject_courier_clears_order_courier_id(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->owner->id,
            'status' => OrderStatus::ASSIGNED,
            'courier_id' => $this->courier->id,
            'total_price' => 100,
            'purchase_price' => 50,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'user_id' => $this->courier->id,
            'type' => 'courier',
        ]);

        $assigner = app(OrderAssigner::class);
        $assigner->rejectCourier($order->id, $this->courier->id, $this->courier);

        $order->refresh();
        $this->assertNull($order->courier_id);
        $this->assertEquals(OrderStatus::ACCEPTED, $order->status);
        $this->assertNull($order->delivery()->first()->user_id);
    }

    public function test_cannot_create_duplicate_active_orders_with_same_external_id(): void
    {
        $orderCreator = app(OrderCreator::class);

        $dto1 = CreateOrderDto::fromArray($this->site->id, [
            'external_id' => 'EXT-UNIQUE-TEST-1',
            'client_name' => 'Клиент 1',
            'client_phone' => '+375291112233',
            'order_items' => [
                ['name' => 'Товар 1', 'price' => 100, 'qty' => 1],
            ],
        ]);

        $dto2 = CreateOrderDto::fromArray($this->site->id, [
            'external_id' => 'EXT-UNIQUE-TEST-1',
            'client_name' => 'Клиент 2',
            'client_phone' => '+375299998877',
            'order_items' => [
                ['name' => 'Товар 1', 'price' => 100, 'qty' => 1],
            ],
        ]);

        $order1 = $orderCreator->createFromDto($dto1);
        $order2 = $orderCreator->createFromDto($dto2);

        $this->assertEquals($order1->id, $order2->id);
        $this->assertEquals(1, Order::where('site_id', $this->site->id)->where('external_id', 'EXT-UNIQUE-TEST-1')->count());
    }

    public function test_incoming_order_job_uses_external_id_lock(): void
    {
        $orderCreator = app(OrderCreator::class);

        $payload = [
            'external_id' => 'EXT-JOB-LOCK-1',
            'client_name' => 'Клиент Тест',
            'client_phone' => '+375291234567',
            'order_items' => [
                ['name' => 'Товар', 'price' => 50, 'qty' => 1],
            ],
        ];

        $job1 = new ProcessIncomingOrderJob($this->site->id, $payload);
        $job1->handle($orderCreator);

        $job2 = new ProcessIncomingOrderJob($this->site->id, $payload);
        $job2->handle($orderCreator);

        $this->assertEquals(1, Order::where('site_id', $this->site->id)->where('external_id', 'EXT-JOB-LOCK-1')->count());
    }

    public function test_customer_phone_uniqueness_validation_on_update(): void
    {
        $customer1 = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Customer 1',
            'phone' => '+375291112233',
        ]);

        $customer2 = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Customer 2',
            'phone' => '+375294445566',
        ]);

        $responsePass = $this->actingAs($this->owner)->patchJson("/api/customers/{$customer2->id}", [
            'name' => 'Customer 2 Updated',
            'phone' => '+375294445566',
        ]);
        $responsePass->assertOk();

        $responseFail = $this->actingAs($this->owner)->patchJson("/api/customers/{$customer2->id}", [
            'phone' => '+375291112233',
        ]);
        $responseFail->assertStatus(422);
        $responseFail->assertJsonValidationErrors(['phone']);
    }

    public function test_order_update_api_returns_loaded_relations(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->owner->id,
            'status' => OrderStatus::ACCEPTED,
            'total_price' => 20,
            'purchase_price' => 10,
        ]);

        $order->items()->create([
            'name' => 'Товар 1',
            'qty' => 2,
            'price' => 10,
        ]);

        $response = $this->actingAs($this->owner)->patchJson("/api/orders/{$order->id}", [
            'total_price' => 25.0,
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('order.items'));
        $this->assertEquals('Товар 1', $response->json('order.items.0.name'));
    }

    public function test_delete_photo_proof_prevents_arbitrary_file_deletion(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('secret/config.json', 'secret-content');

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->owner->id,
            'status' => OrderStatus::ACCEPTED,
            'total_price' => 50,
            'purchase_price' => 25,
        ]);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'user_id' => $this->owner->id,
            'type' => 'courier',
            'photo_proof_path' => 'delivery-proofs/legit.jpg',
            'photo_proof_paths' => ['delivery-proofs/legit.jpg'],
        ]);
        Storage::disk('public')->put('delivery-proofs/legit.jpg', 'legit-image');

        $delivery->deletePhotoProofFile('secret/config.json');

        $this->assertTrue(Storage::disk('public')->exists('secret/config.json'));
        $this->assertTrue(Storage::disk('public')->exists('delivery-proofs/legit.jpg'));
    }

    public function test_desktop_link_is_protected_by_rate_limiter(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->owner)->postJson('/api/auth/desktop-link');
            $this->assertTrue(in_array($response->status(), [200, 429], true));
        }

        $throttled = $this->actingAs($this->owner)->postJson('/api/auth/desktop-link');
        $this->assertEquals(429, $throttled->status());
    }

    public function test_external_link_email_sanitizes_invalid_email(): void
    {
        $response = $this->get('/open/email?to=invalid-email<script>');
        $response->assertOk();
        $response->assertSee('href="#"', false);
    }

    public function test_incoming_lead_deduplicates_by_external_id_and_locks(): void
    {
        $job1 = new ProcessIncomingLeadJob($this->site->id, [
            'external_id' => 'LEAD-EXT-100',
            'client_name' => 'Лид Тест 1',
            'client_phone' => '+375291112233',
        ]);
        $job1->handle(app(CustomerResolver::class), app(TelegramService::class));

        $this->assertEquals(1, Lead::where('site_id', $this->site->id)->where('external_id', 'LEAD-EXT-100')->count());

        $job2 = new ProcessIncomingLeadJob($this->site->id, [
            'external_id' => 'LEAD-EXT-100',
            'client_name' => 'Лид Тест 2',
            'client_phone' => '+375291112233',
        ]);
        $job2->handle(app(CustomerResolver::class), app(TelegramService::class));

        $this->assertEquals(1, Lead::where('site_id', $this->site->id)->where('external_id', 'LEAD-EXT-100')->count());
    }

    public function test_customer_resolver_handles_email_only_without_duplicate(): void
    {
        $resolver = app(CustomerResolver::class);

        $customer1 = $resolver->resolve($this->site->id, [
            'client_name' => 'Email Customer',
            'client_email' => 'unique-email@example.com',
        ]);
        $this->assertNotNull($customer1);

        $customer2 = $resolver->resolve($this->site->id, [
            'client_name' => 'Email Customer Updated',
            'client_email' => 'unique-email@example.com',
        ]);
        $this->assertEquals($customer1->id, $customer2->id);
    }

    public function test_order_status_machine_validates_transition_and_valuable_order_signature(): void
    {
        $machine = app(OrderStatusMachine::class);

        $manager = User::create([
            'role' => UserRole::MANAGER,
            'system_name' => 'Manager Test',
            'telegram_user_id' => '333333',
            'is_active' => true,
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $manager->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 100,
            'purchase_price' => 50,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Некорректный переход статуса');
        $machine->changeStatus($order->id, OrderStatus::CREATED, $manager->id);
    }

    public function test_order_status_machine_blocks_completing_valuable_order_without_signature(): void
    {
        $machine = app(OrderStatusMachine::class);

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->owner->id,
            'status' => OrderStatus::ASSIGNED,
            'total_price' => 60000,
            'purchase_price' => 30000,
            'is_valuable' => true,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'user_id' => $this->courier->id,
            'type' => 'courier',
            'signature_path' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Для ценного отправления требуется подпись клиента');
        $machine->changeStatus($order->id, OrderStatus::COMPLETED, $this->owner->id);
    }

    public function test_product_deletion_is_atomic_and_archives_when_used_in_orders(): void
    {
        $product = Product::create([
            'site_id' => $this->site->id,
            'name' => 'Test Product For Deletion',
            'price' => 100,
            'is_active' => true,
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->owner->id,
            'status' => OrderStatus::CREATED,
            'total_price' => 100,
            'purchase_price' => 50,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => 1,
            'price' => 100,
            'purchase_price' => 50,
        ]);

        $response = $this->actingAs($this->owner)->deleteJson("/api/products/{$product->id}");
        $response->assertOk();
        $response->assertJson(['message' => 'Товар архивирован, так как он используется в заказах']);

        $product->refresh();
        $this->assertFalse($product->is_active);
    }

    public function test_wipe_table_names_includes_product_variants(): void
    {
        $tables = DataRetentionService::wipeTableNames();
        $this->assertContains('product_variants', $tables);
        $this->assertContains('products', $tables);
    }
}
