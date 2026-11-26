<?php

namespace Tests\Feature\Orders;

use App\Enums\UserRole;
use App\Jobs\ProcessIncomingOrderJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IncomingOrderWithVariantsTest extends TestCase
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

    public function test_incoming_order_resolves_product_and_variant_by_external_variant_id(): void
    {
        $site = $this->createSite();

        $product = Product::create([
            'site_id' => $site->id,
            'external_id' => 'site-prod-1',
            'name' => 'Футболка Classic',
            'image_url' => 'https://site.com/tshirt.jpg',
            'price' => 1200,
            'cost_price' => 500,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'external_id' => 'site-var-1',
            'sku' => 'TSHIRT-RED-M',
            'title' => 'Красный / M',
            'price' => 1250,
            'purchase_price' => 550,
        ]);

        $payload = [
            'client_name' => 'Иван Иванов',
            'client_phone' => '+375 29 111-22-33',
            'order_items' => [
                [
                    'external_variant_id' => 'site-var-1',
                    'qty' => 2,
                ],
            ],
        ];

        $response = $this->withHeaders(['X-API-Token' => $site->api_token])
            ->postJson('/api/orders/incoming', $payload);

        $response->assertStatus(202);

        ProcessIncomingOrderJob::dispatchSync($site->id, $payload);

        $order = Order::where('site_id', $site->id)->latest('id')->first();
        $this->assertNotNull($order);

        $item = $order->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals($product->id, $item->product_id);
        $this->assertEquals('site-prod-1', $item->external_product_id);
        $this->assertEquals($variant->id, $item->product_variant_id);
        $this->assertEquals('site-var-1', $item->external_variant_id);
        $this->assertEquals('Футболка Classic', $item->name);
        $this->assertEquals('Красный / M', $item->variant_title);
        $this->assertEquals('TSHIRT-RED-M', $item->sku);
        $this->assertEquals(2, $item->qty);
        $this->assertEquals(1250.00, (float) $item->price);
        $this->assertEquals(550.00, (float) $item->purchase_price);

        // Check Order API response serialization
        $user = User::create([
            'first_name' => 'Owner',
            'system_name' => 'Owner',
            'telegram_user_id' => 99999,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $showResponse = $this->actingAs($user)
            ->getJson("/api/orders/{$order->id}");

        $showResponse->assertStatus(200)
            ->assertJsonPath('order.items.0.external_product_id', 'site-prod-1')
            ->assertJsonPath('order.items.0.external_variant_id', 'site-var-1')
            ->assertJsonPath('order.items.0.image_url', 'https://site.com/tshirt.jpg')
            ->assertJsonPath('order.items.0.variant_title', 'Красный / M');
    }

    public function test_incoming_order_resolves_variant_by_sku(): void
    {
        $site = $this->createSite();

        $product = Product::create([
            'site_id' => $site->id,
            'external_id' => 'site-prod-2',
            'name' => 'Джинсы Slim',
            'price' => 3000,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'external_id' => 'site-var-2',
            'sku' => 'JEANS-BLU-32',
            'title' => 'Синий / 32',
            'price' => 3200,
            'purchase_price' => 1500,
        ]);

        $payload = [
            'client_name' => 'Петр Петров',
            'client_phone' => '+375 29 222-33-44',
            'order_items' => [
                [
                    'sku' => 'JEANS-BLU-32',
                    'qty' => 1,
                ],
            ],
        ];

        $response = $this->withHeaders(['X-API-Token' => $site->api_token])
            ->postJson('/api/orders/incoming', $payload);

        $response->assertStatus(202);

        ProcessIncomingOrderJob::dispatchSync($site->id, $payload);

        $order = Order::where('site_id', $site->id)->latest('id')->first();
        $this->assertNotNull($order);

        $item = $order->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals($product->id, $item->product_id);
        $this->assertEquals($variant->id, $item->product_variant_id);
        $this->assertEquals('site-prod-2', $item->external_product_id);
        $this->assertEquals('site-var-2', $item->external_variant_id);
        $this->assertEquals('Синий / 32', $item->variant_title);
        $this->assertEquals(3200.00, (float) $item->price);
    }

    public function test_incoming_order_resolves_base_product_by_sku_without_variants(): void
    {
        $site = $this->createSite();

        $product = Product::create([
            'site_id' => $site->id,
            'external_id' => 'site-base-prod-3',
            'sku' => 'SOLO-ITEM-SKU',
            'name' => 'Одиночный товар',
            'image_url' => 'https://site.com/solo.jpg',
            'price' => 750,
            'cost_price' => 300,
        ]);

        $payload = [
            'client_name' => 'Петр Петров',
            'client_phone' => '+375 29 777-88-99',
            'order_items' => [
                [
                    'sku' => 'SOLO-ITEM-SKU',
                    'qty' => 1,
                ],
            ],
        ];

        $response = $this->withHeaders(['X-API-Token' => $site->api_token])
            ->postJson('/api/orders/incoming', $payload);

        $response->assertStatus(202);

        ProcessIncomingOrderJob::dispatchSync($site->id, $payload);

        $order = Order::where('site_id', $site->id)->latest('id')->first();
        $this->assertNotNull($order);

        $item = $order->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals($product->id, $item->product_id);
        $this->assertNull($item->product_variant_id);
        $this->assertEquals('site-base-prod-3', $item->external_product_id);
        $this->assertEquals('Одиночный товар', $item->name);
        $this->assertEquals(750.00, (float) $item->price);
        $this->assertEquals(300.00, (float) $item->purchase_price);
    }
}
