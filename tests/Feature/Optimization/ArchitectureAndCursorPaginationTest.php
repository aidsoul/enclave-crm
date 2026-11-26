<?php

namespace Tests\Feature\Optimization;

use App\Dto\Lead\CreateLeadDto;
use App\Dto\Order\CreateOrderDto;
use App\Dto\Order\UpdateOrderDto;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\OrderCreator;
use App\Services\Orders\OrderUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchitectureAndCursorPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner',
            'telegram_user_id' => 10001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Test Site',
            'domain' => 'test.example.com',
            'is_active' => true,
            'token' => 'test-site-token',
        ]);
    }

    public function test_create_order_dto_instantiation_and_conversion(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'client_name' => 'Alex Test',
            'client_phone' => '+380501234567',
            'total_price' => 1250.50,
            'delivery_type' => 'courier',
            'delivery_city' => 'Kyiv',
            'order_items' => [
                ['name' => 'Item 1', 'price' => 1250.50, 'qty' => 1],
            ],
        ];

        $dto = CreateOrderDto::fromArray($this->site->id, $payload, $this->owner->id);

        $this->assertSame($this->site->id, $dto->siteId);
        $this->assertSame('Alex Test', $dto->clientName);
        $this->assertSame('+380501234567', $dto->clientPhone);
        $this->assertSame(1250.50, $dto->totalPrice);
        $this->assertSame($this->owner->id, $dto->actorId);
        $this->assertSame($payload, $dto->toArray());

        $creator = app(OrderCreator::class);
        $order = $creator->createFromDto($dto);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(1250.50, (float) $order->total_price);
        $this->assertSame(OrderStatus::CREATED, $order->status);
    }

    public function test_update_order_dto_execution(): void
    {
        $creator = app(OrderCreator::class);
        $order = $creator->create($this->site->id, [
            'client_name' => 'Initial Name',
            'total_price' => 500,
        ], $this->owner->id);

        $updateDto = UpdateOrderDto::fromArray($order->id, [
            'total_price' => 750,
        ], $this->owner->id);

        $updater = app(OrderUpdater::class);
        $updatedOrder = $updater->updateFromDto($updateDto);

        $this->assertSame(750.0, (float) $updatedOrder->total_price);
    }

    public function test_create_lead_dto_instantiation(): void
    {
        $data = [
            'type' => 'callback',
            'client_name' => 'Lead User',
            'client_phone' => '+380991112233',
            'comment' => 'Please call me',
        ];

        $dto = CreateLeadDto::fromArray($this->site->id, $data);

        $this->assertSame($this->site->id, $dto->siteId);
        $this->assertSame('callback', $dto->type);
        $this->assertSame('Lead User', $dto->name);
        $this->assertSame('+380991112233', $dto->phone);
        $this->assertSame('Please call me', $dto->comment);
        $this->assertSame($data, $dto->toArray());
    }

    public function test_orders_cursor_pagination_and_standard_pagination(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Order::create([
                'site_id' => $this->site->id,
                'status' => OrderStatus::CREATED,
                'total_price' => 100 * $i,
                'purchase_price' => 50 * $i,
                'created_at' => now()->subMinutes(15 - $i),
            ]);
        }

        $standardResponse = $this->actingAs($this->owner)
            ->getJson('/api/orders?page=1&per_page=5');

        $standardResponse->assertOk()
            ->assertJsonStructure([
                'orders',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);
        $this->assertCount(5, $standardResponse->json('orders'));
        $this->assertSame(15, $standardResponse->json('total'));

        $cursorResponse = $this->actingAs($this->owner)
            ->getJson('/api/orders?cursor=default&per_page=5');

        $cursorResponse->assertOk()
            ->assertJsonStructure([
                'orders',
                'per_page',
                'has_more',
            ]);
        $this->assertCount(5, $cursorResponse->json('orders'));
        $this->assertTrue($cursorResponse->json('has_more'));
    }
}
