<?php

namespace Tests\Feature\Analytics;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductKpiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected User $courier;

    protected Site $site;

    protected Customer $customer;

    protected Product $productA;

    protected Product $productB;

    protected Product $productC;

    protected function setUp(): void
    {
        parent::setUp();

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
            'system_name' => 'Courier Dmitry',
            'telegram_user_id' => 10003,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Store 1',
            'domain' => 'store1.example.com',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Customer Test',
            'phone' => '+79991112233',
        ]);

        $this->productA = Product::create([
            'site_id' => $this->site->id,
            'name' => 'Product Alpha',
            'sku' => 'ALPHA-01',
            'price' => 1000,
            'cost_price' => 600,
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'site_id' => $this->site->id,
            'name' => 'Product Beta',
            'sku' => 'BETA-02',
            'price' => 2000,
            'cost_price' => 1200,
            'is_active' => true,
        ]);

        $this->productC = Product::create([
            'site_id' => $this->site->id,
            'name' => 'Product Gamma',
            'sku' => 'GAMMA-03',
            'price' => 500,
            'cost_price' => 250,
            'is_active' => true,
        ]);
    }

    public function test_api_dashboard_products_kpi_endpoint_requires_owner(): void
    {
        $this->getJson('/api/dashboard/products-kpi')
            ->assertStatus(401);

        $this->actingAs($this->courier)
            ->getJson('/api/dashboard/products-kpi')
            ->assertStatus(403);

        $this->actingAs($this->manager)
            ->getJson('/api/dashboard/products-kpi')
            ->assertStatus(403);

        $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi')
            ->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'date_from',
                'date_to',
                'summary' => [
                    'total_revenue',
                    'total_profit',
                    'total_units_sold',
                    'total_units_ordered',
                    'unique_products_sold',
                    'avg_margin_rate',
                ],
                'pagination' => [
                    'total',
                    'page',
                    'per_page',
                    'has_more',
                    'remaining',
                ],
                'products',
            ]);
    }

    public function test_product_sales_analytics_calculation(): void
    {
        $order1 = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 4000,
            'purchase_price' => 2400,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $this->productA->id,
            'name' => $this->productA->name,
            'sku' => $this->productA->sku,
            'qty' => 2,
            'price' => 1000,
            'purchase_price' => 600,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $this->productB->id,
            'name' => $this->productB->name,
            'sku' => $this->productB->sku,
            'qty' => 1,
            'price' => 2000,
            'purchase_price' => 1200,
        ]);

        $order2 = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::CANCELLED,
            'total_price' => 1000,
            'purchase_price' => 600,
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $this->productA->id,
            'name' => $this->productA->name,
            'sku' => $this->productA->sku,
            'qty' => 1,
            'price' => 1000,
            'purchase_price' => 600,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi?period=today')
            ->assertStatus(200);

        $json = $response->json();

        $this->assertEquals(4000.0, $json['summary']['total_revenue']);
        $this->assertEquals(1600.0, $json['summary']['total_profit']);
        $this->assertEquals(3, $json['summary']['total_units_sold']);
        $this->assertEquals(2, $json['summary']['unique_products_sold']);
        $this->assertEquals(40.0, $json['summary']['avg_margin_rate']);

        $products = collect($json['products']);

        $itemA = $products->firstWhere('id', $this->productA->id);
        $this->assertNotNull($itemA);
        $this->assertEquals(2000.0, $itemA['revenue']);
        $this->assertEquals(800.0, $itemA['profit']);
        $this->assertEquals(1, $itemA['orders_count']);
        $this->assertEquals(2, $itemA['completed_qty']);
        $this->assertEquals(1, $itemA['cancelled_qty']);
        $this->assertEquals(2, $itemA['total_qty']);
        $this->assertEquals(40.0, $itemA['margin_rate']);
        $this->assertEquals(66.7, $itemA['buyout_rate']);

        $itemB = $products->firstWhere('id', $this->productB->id);
        $this->assertNotNull($itemB);
        $this->assertEquals(2000.0, $itemB['revenue']);
        $this->assertEquals(800.0, $itemB['profit']);
        $this->assertEquals(1, $itemB['orders_count']);
        $this->assertEquals(1, $itemB['completed_qty']);
        $this->assertEquals(0, $itemB['cancelled_qty']);
        $this->assertEquals(100.0, $itemB['buyout_rate']);

        $itemC = $products->firstWhere('id', $this->productC->id);
        $this->assertNotNull($itemC);
        $this->assertEquals(0.0, $itemC['revenue']);
        $this->assertEquals(0, $itemC['orders_count']);
        $this->assertEquals(0, $itemC['total_qty']);
    }

    public function test_search_and_filter_and_sort(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 1000,
            'purchase_price' => 600,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'name' => $this->productA->name,
            'sku' => $this->productA->sku,
            'qty' => 1,
            'price' => 1000,
            'purchase_price' => 600,
        ]);

        $searchRes = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi?search=ALPHA')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $searchRes['products']);
        $this->assertEquals($this->productA->id, $searchRes['products'][0]['id']);

        $filterWithSales = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi?filter=with_sales')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $filterWithSales['products']);
        $this->assertEquals($this->productA->id, $filterWithSales['products'][0]['id']);

        $filterWithoutSales = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi?filter=without_sales')
            ->assertStatus(200)
            ->json();

        $this->assertCount(2, $filterWithoutSales['products']);

        $sortName = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi?sort=name_desc')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($this->productC->id, $sortName['products'][0]['id']);
    }

    public function test_pagination_limits_clamped(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi?per_page=999')
            ->assertStatus(200)
            ->json();

        $this->assertEquals(100, $response['pagination']['per_page']);

        $responseZero = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi?per_page=-5')
            ->assertStatus(200)
            ->json();

        $this->assertEquals(1, $responseZero['pagination']['per_page']);
    }

    public function test_inactive_product_handling(): void
    {
        $inactiveNoSales = Product::create([
            'site_id' => $this->site->id,
            'name' => 'Archived Product No Sales',
            'sku' => 'ARCH-01',
            'price' => 100,
            'cost_price' => 50,
            'is_active' => false,
        ]);

        $inactiveWithSales = Product::create([
            'site_id' => $this->site->id,
            'name' => 'Archived Product With Sales',
            'sku' => 'ARCH-02',
            'price' => 1500,
            'cost_price' => 800,
            'is_active' => false,
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::COMPLETED,
            'total_price' => 1500,
            'purchase_price' => 800,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $inactiveWithSales->id,
            'name' => $inactiveWithSales->name,
            'sku' => $inactiveWithSales->sku,
            'qty' => 1,
            'price' => 1500,
            'purchase_price' => 800,
        ]);

        $allRes = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi')
            ->assertStatus(200)
            ->json();

        $ids = collect($allRes['products'])->pluck('id');
        $this->assertTrue($ids->contains($inactiveWithSales->id));
        $this->assertFalse($ids->contains($inactiveNoSales->id));

        $withoutSales = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/products-kpi?filter=without_sales')
            ->assertStatus(200)
            ->json();

        $withoutIds = collect($withoutSales['products'])->pluck('id');
        $this->assertFalse($withoutIds->contains($inactiveNoSales->id));
    }
}
