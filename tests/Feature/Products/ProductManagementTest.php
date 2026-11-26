<?php

namespace Tests\Feature\Products;

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

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected User $courier;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 11001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Olga',
            'telegram_user_id' => 11002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Alex',
            'telegram_user_id' => 11003,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Main Shop',
            'domain' => 'shop.test',
        ]);
    }

    public function test_creates_product_successfully(): void
    {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/products', [
                'name' => 'Наушники Pro',
                'sku' => 'HP-001',
                'price' => 199.99,
                'cost_price' => 120.00,
                'site_id' => $this->site->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('product.name', 'Наушники Pro')
            ->assertJsonPath('product.sku', 'HP-001')
            ->assertJsonPath('product.price', 199.99)
            ->assertJsonPath('product.cost_price', 120)
            ->assertJsonPath('product.margin', 79.99);

        $this->assertDatabaseHas('products', [
            'name' => 'Наушники Pro',
            'sku' => 'HP-001',
        ]);
    }

    public function test_validates_product_creation(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price']);
    }

    public function test_lists_and_searches_products(): void
    {
        Product::create([
            'name' => 'Чехол силиконовый iPhone',
            'sku' => 'CASE-IP-SIL',
            'price' => 25.00,
            'cost_price' => 8.00,
            'site_id' => $this->site->id,
        ]);

        Product::create([
            'name' => 'Кабель Type-C',
            'sku' => 'CABLE-TC',
            'price' => 15.00,
            'cost_price' => 4.00,
            'site_id' => $this->site->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/products?search=чехол');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.sku', 'CASE-IP-SIL');
    }

    public function test_updates_product(): void
    {
        $product = Product::create([
            'name' => 'Старое название',
            'sku' => 'OLD-SKU',
            'price' => 50.00,
            'cost_price' => 30.00,
        ]);

        $this->actingAs($this->manager)
            ->patchJson("/api/products/{$product->id}", [
                'name' => 'Новое название',
                'price' => 60.00,
            ])
            ->assertStatus(200)
            ->assertJsonPath('product.name', 'Новое название')
            ->assertJsonPath('product.price', 60);

        $this->assertEquals('Новое название', $product->fresh()->name);
    }

    public function test_archives_product_if_used_in_orders(): void
    {
        $product = Product::create([
            'name' => 'Товар в заказе',
            'price' => 100.00,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Client 1',
            'phone' => '+375291112233',
            'site_id' => $this->site->id,
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $customer->id,
            'total_price' => 100.00,
            'purchase_price' => 60.00,
            'status' => OrderStatus::CREATED,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => 1,
            'price' => 100.00,
        ]);

        $this->actingAs($this->manager)
            ->deleteJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Товар архивирован, так как он используется в заказах');

        $this->assertFalse($product->fresh()->is_active);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_deletes_unused_product(): void
    {
        $product = Product::create([
            'name' => 'Неиспользуемый товар',
            'price' => 10.00,
        ]);

        $this->actingAs($this->manager)
            ->deleteJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Товар удален');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_courier_cannot_manage_products(): void
    {
        $this->actingAs($this->courier)
            ->getJson('/api/products')
            ->assertStatus(403);
    }
}
