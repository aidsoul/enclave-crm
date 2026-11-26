<?php

namespace Tests\Feature\Catalog;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SingleProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createSite(): Site
    {
        return Site::create([
            'name' => 'Тест Сайт',
            'domain' => 'test-site-'.Str::random(5).'.com',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    public function test_can_create_or_update_single_product_with_variants(): void
    {
        $site = $this->createSite();

        $payload = [
            'external_id' => 'sp-1',
            'name' => 'Кроссовки Runner',
            'sku' => 'RUN-01',
            'image_url' => 'https://site.com/shoes.jpg',
            'price' => 5000.00,
            'cost_price' => 2500.00,
            'variants' => [
                [
                    'external_id' => 'sp-1-v1',
                    'sku' => 'RUN-01-42',
                    'title' => 'Размер 42',
                    'price' => 5000.00,
                ],
                [
                    'external_id' => 'sp-1-v2',
                    'sku' => 'RUN-01-43',
                    'title' => 'Размер 43',
                    'price' => 5200.00,
                ],
            ],
        ];

        $response = $this->withHeaders(['X-API-Token' => $site->api_token])
            ->postJson('/api/catalog/products', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('product.name', 'Кроссовки Runner')
            ->assertJsonPath('product.image_url', 'https://site.com/shoes.jpg')
            ->assertJsonCount(2, 'product.variants');

        $this->assertDatabaseHas('products', [
            'site_id' => $site->id,
            'external_id' => 'sp-1',
            'name' => 'Кроссовки Runner',
            'image_url' => 'https://site.com/shoes.jpg',
        ]);

        $this->assertDatabaseHas('product_variants', [
            'external_id' => 'sp-1-v1',
            'sku' => 'RUN-01-42',
        ]);
    }

    public function test_can_delete_unused_product(): void
    {
        $site = $this->createSite();

        $product = Product::create([
            'site_id' => $site->id,
            'external_id' => 'del-1',
            'name' => 'Товар для удаления',
        ]);

        $response = $this->withHeaders(['X-API-Token' => $site->api_token])
            ->deleteJson('/api/catalog/products/del-1');

        $response->assertStatus(200)
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_archives_product_if_used_in_orders(): void
    {
        $site = $this->createSite();

        $product = Product::create([
            'site_id' => $site->id,
            'external_id' => 'used-1',
            'name' => 'Используемый товар',
            'is_active' => true,
        ]);

        $order = Order::create([
            'site_id' => $site->id,
            'total_price' => 100,
            'purchase_price' => 50,
            'status' => 'created',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => 1,
            'price' => 100,
        ]);

        $response = $this->withHeaders(['X-API-Token' => $site->api_token])
            ->deleteJson('/api/catalog/products/used-1');

        $response->assertStatus(200)
            ->assertJson(['archived' => true]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);
    }

    public function test_manager_can_view_product_with_variants_via_api(): void
    {
        $site = $this->createSite();
        $manager = User::create([
            'telegram_user_id' => 11223344,
            'system_name' => 'manager_view_prod',
            'role' => 'manager',
            'is_active' => true,
        ]);

        $product = Product::create([
            'site_id' => $site->id,
            'external_id' => 'prod-view-1',
            'name' => 'Смартфон Nova',
            'sku' => 'NOVA-01',
            'image_url' => 'https://site.com/nova.jpg',
            'url' => 'https://site.com/p/nova',
            'price' => 1200.00,
            'cost_price' => 800.00,
            'is_active' => true,
        ]);

        $product->variants()->create([
            'external_id' => 'prod-view-1-black',
            'sku' => 'NOVA-01-BLK',
            'title' => 'Черный 128GB',
            'price' => 1200.00,
            'purchase_price' => 800.00,
            'attributes' => ['color' => 'Черный', 'storage' => '128GB'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/api/products/'.$product->id);

        $response->assertStatus(200)
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('product.name', 'Смартфон Nova')
            ->assertJsonPath('product.sku', 'NOVA-01')
            ->assertJsonPath('product.image_url', 'https://site.com/nova.jpg')
            ->assertJsonPath('product.url', 'https://site.com/p/nova')
            ->assertJsonPath('product.cost_price', 800)
            ->assertJsonPath('product.margin', 400)
            ->assertJsonCount(1, 'product.variants')
            ->assertJsonPath('product.variants.0.title', 'Черный 128GB')
            ->assertJsonPath('product.variants.0.attributes.color', 'Черный');
    }

    public function test_customer_show_api_returns_order_item_product_id(): void
    {
        $site = $this->createSite();
        $manager = User::create([
            'telegram_user_id' => 55667788,
            'system_name' => 'manager_view_cust',
            'role' => 'manager',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'site_id' => $site->id,
            'phone' => '+375291234567',
            'name' => 'Иван Иванов',
        ]);

        $product = Product::create([
            'site_id' => $site->id,
            'external_id' => 'cust-prod-1',
            'name' => 'Телевизор 55',
            'price' => 2000,
            'cost_price' => 1400,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'total_price' => 2000,
            'purchase_price' => 1400,
            'status' => 'created',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => 1,
            'price' => 2000,
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/api/customers/'.$customer->id);

        $response->assertStatus(200)
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonCount(1, 'customer.orders')
            ->assertJsonPath('customer.orders.0.items.0.product_id', $product->id)
            ->assertJsonPath('customer.orders.0.items.0.name', 'Телевизор 55');
    }
}
