<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderFilterAndSortTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected User $courier;

    protected Site $site1;

    protected Site $site2;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner',
            'telegram_user_id' => 10001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager 1',
            'telegram_user_id' => 20001,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier 1',
            'telegram_user_id' => 30001,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->site1 = Site::create([
            'name' => 'Site A',
            'domain' => 'site-a.example.com',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $this->site2 = Site::create([
            'name' => 'Site B',
            'domain' => 'site-b.example.com',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'site_id' => $this->site1->id,
            'name' => 'Test Customer',
            'phone' => '+79990001122',
        ]);
    }

    public function test_can_sort_orders_by_total_price(): void
    {
        Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 100.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);

        Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 500.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);

        Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 250.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);

        $resAsc = $this->actingAs($this->owner)
            ->getJson('/api/orders?sort_by=total_price&sort_order=asc&page=1')
            ->assertOk();

        $pricesAsc = collect($resAsc->json('orders'))->pluck('total_price')->map(fn ($p) => (float) $p)->all();
        $this->assertEquals([100.00, 250.00, 500.00], $pricesAsc);

        $resDesc = $this->actingAs($this->owner)
            ->getJson('/api/orders?sort_by=total_price&sort_order=desc&page=1')
            ->assertOk();

        $pricesDesc = collect($resDesc->json('orders'))->pluck('total_price')->map(fn ($p) => (float) $p)->all();
        $this->assertEquals([500.00, 250.00, 100.00], $pricesDesc);
    }

    public function test_can_filter_orders_by_site(): void
    {
        $o1 = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 100.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);

        $o2 = Order::create([
            'site_id' => $this->site2->id,
            'customer_id' => $this->customer->id,
            'total_price' => 200.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);

        $res = $this->actingAs($this->owner)
            ->getJson("/api/orders?site_id={$this->site1->id}")
            ->assertOk();

        $ids = collect($res->json('orders'))->pluck('id')->all();
        $this->assertContains($o1->id, $ids);
        $this->assertNotContains($o2->id, $ids);
    }

    public function test_can_filter_orders_by_manager_assigned_and_unassigned(): void
    {
        $assignedOrder = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'total_price' => 100.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::ACCEPTED,
        ]);

        $unassignedOrder = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'manager_id' => null,
            'total_price' => 200.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);

        $resAssigned = $this->actingAs($this->owner)
            ->getJson("/api/orders?manager_id={$this->manager->id}")
            ->assertOk();

        $assignedIds = collect($resAssigned->json('orders'))->pluck('id')->all();
        $this->assertContains($assignedOrder->id, $assignedIds);
        $this->assertNotContains($unassignedOrder->id, $assignedIds);

        $resUnassigned = $this->actingAs($this->owner)
            ->getJson('/api/orders?manager_id=unassigned')
            ->assertOk();

        $unassignedIds = collect($resUnassigned->json('orders'))->pluck('id')->all();
        $this->assertContains($unassignedOrder->id, $unassignedIds);
        $this->assertNotContains($assignedOrder->id, $unassignedIds);
    }

    public function test_can_filter_orders_by_courier(): void
    {
        $courierOrder = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courier->id,
            'total_price' => 100.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::ASSIGNED,
        ]);

        $otherOrder = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'courier_id' => null,
            'total_price' => 200.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);

        $res = $this->actingAs($this->owner)
            ->getJson("/api/orders?courier_id={$this->courier->id}")
            ->assertOk();

        $ids = collect($res->json('orders'))->pluck('id')->all();
        $this->assertContains($courierOrder->id, $ids);
        $this->assertNotContains($otherOrder->id, $ids);
    }

    public function test_can_filter_orders_by_date_range_and_price_range(): void
    {
        Carbon::setTestNow('2026-10-25 12:00:00');

        $o1 = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 150.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);
        $o1->created_at = Carbon::parse('2026-10-20 10:00:00');
        $o1->save();

        $o2 = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 800.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);
        $o2->created_at = Carbon::parse('2026-10-25 10:00:00');
        $o2->save();

        $o3 = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 50.00,
            'purchase_price' => 20.00,
            'status' => OrderStatus::CREATED,
        ]);
        $o3->created_at = Carbon::parse('2026-10-25 11:00:00');
        $o3->save();

        // Date range filter
        $resDate = $this->actingAs($this->owner)
            ->getJson('/api/orders?date_from=2026-10-24&date_to=2026-10-25')
            ->assertOk();

        $dateIds = collect($resDate->json('orders'))->pluck('id')->all();
        $this->assertContains($o2->id, $dateIds);
        $this->assertContains($o3->id, $dateIds);
        $this->assertNotContains($o1->id, $dateIds);

        // Price range filter
        $resPrice = $this->actingAs($this->owner)
            ->getJson('/api/orders?min_price=100&max_price=500')
            ->assertOk();

        $priceIds = collect($resPrice->json('orders'))->pluck('id')->all();
        $this->assertContains($o1->id, $priceIds);
        $this->assertNotContains($o2->id, $priceIds);
        $this->assertNotContains($o3->id, $priceIds);

        Carbon::setTestNow();
    }

    public function test_can_sort_orders_by_delivery_date_and_delivery_slot(): void
    {
        $o1 = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 100.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::ASSIGNED,
        ]);
        Delivery::create([
            'order_id' => $o1->id,
            'type' => DeliveryType::COURIER,
            'delivery_date' => '2026-11-15',
            'delivery_slot' => '14:00–18:00',
        ]);

        $o2 = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 200.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::ASSIGNED,
        ]);
        Delivery::create([
            'order_id' => $o2->id,
            'type' => DeliveryType::COURIER,
            'delivery_date' => '2026-11-15',
            'delivery_slot' => '10:00–12:00',
        ]);

        $o3 = Order::create([
            'site_id' => $this->site1->id,
            'customer_id' => $this->customer->id,
            'total_price' => 300.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::ASSIGNED,
        ]);
        Delivery::create([
            'order_id' => $o3->id,
            'type' => DeliveryType::COURIER,
            'delivery_date' => '2026-11-16',
            'delivery_slot' => '09:00–12:00',
        ]);

        $res = $this->actingAs($this->owner)
            ->getJson('/api/orders?sort_by=delivery_slot&sort_order=asc')
            ->assertOk();

        $ids = collect($res->json('orders'))->pluck('id')->all();
        $this->assertEquals([$o2->id, $o1->id, $o3->id], array_slice($ids, 0, 3));

        $resDesc = $this->actingAs($this->owner)
            ->getJson('/api/orders?sort_by=delivery_slot&sort_order=desc')
            ->assertOk();

        $idsDesc = collect($resDesc->json('orders'))->pluck('id')->all();
        $this->assertEquals([$o3->id, $o1->id, $o2->id], array_slice($idsDesc, 0, 3));
    }
}
