<?php

namespace Tests\Feature\Orders;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderProductSearchAndBulkAddTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected Site $siteA;

    protected Site $siteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::create([
            'system_name' => 'Manager Kate',
            'telegram_user_id' => 99101,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->siteA = Site::create([
            'name' => 'Shop Alpha',
            'domain' => 'alpha.test',
            'is_active' => true,
            'default_purchase_percent' => 20,
        ]);

        $this->siteB = Site::create([
            'name' => 'Shop Beta',
            'domain' => 'beta.test',
            'is_active' => true,
            'default_purchase_percent' => 30,
        ]);
    }

    public function test_product_search_finds_by_name_and_sku(): void
    {
        Product::create([
            'name' => 'Беспроводная мышь Logitech',
            'sku' => 'MOU-LOGI-01',
            'price' => 85.00,
            'cost_price' => 45.00,
            'site_id' => $this->siteA->id,
            'is_active' => true,
        ]);

        $resName = $this->actingAs($this->manager)
            ->getJson("/api/products?site_id={$this->siteA->id}&search=Logitech");

        $resName->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.sku', 'MOU-LOGI-01');

        $resSku = $this->actingAs($this->manager)
            ->getJson("/api/products?site_id={$this->siteA->id}&search=MOU-LOGI");

        $resSku->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'Беспроводная мышь Logitech');
    }

    public function test_product_search_finds_by_variant_title_and_variant_sku(): void
    {
        $product = Product::create([
            'name' => 'Спортивный костюм Nike',
            'sku' => 'SUIT-NIKE',
            'price' => 250.00,
            'cost_price' => 150.00,
            'site_id' => $this->siteA->id,
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'title' => 'Черный / XL',
            'sku' => 'NIKE-BLK-XL',
            'price' => 260.00,
            'purchase_price' => 155.00,
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'title' => 'Серый / M',
            'sku' => 'NIKE-GRY-M',
            'price' => 250.00,
            'purchase_price' => 150.00,
            'is_active' => true,
        ]);

        $resTitle = $this->actingAs($this->manager)
            ->getJson("/api/products?site_id={$this->siteA->id}&search=Черный");

        $resTitle->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $product->id)
            ->assertJsonCount(2, 'products.0.variants');

        $resVarSku = $this->actingAs($this->manager)
            ->getJson("/api/products?site_id={$this->siteA->id}&search=NIKE-GRY");

        $resVarSku->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $product->id);
    }

    public function test_product_search_respects_selected_site(): void
    {
        $prodA = Product::create([
            'name' => 'Товар Сайта А',
            'sku' => 'PROD-A',
            'price' => 100.00,
            'site_id' => $this->siteA->id,
            'is_active' => true,
        ]);

        $prodB = Product::create([
            'name' => 'Товар Сайта Б',
            'sku' => 'PROD-B',
            'price' => 120.00,
            'site_id' => $this->siteB->id,
            'is_active' => true,
        ]);

        $resA = $this->actingAs($this->manager)
            ->getJson("/api/products?site_id={$this->siteA->id}");

        $resA->assertOk();
        $idsA = collect($resA->json('products'))->pluck('id')->all();
        $this->assertContains($prodA->id, $idsA);
        $this->assertNotContains($prodB->id, $idsA);

        $resB = $this->actingAs($this->manager)
            ->getJson("/api/products?site_id={$this->siteB->id}");

        $resB->assertOk();
        $idsB = collect($resB->json('products'))->pluck('id')->all();
        $this->assertContains($prodB->id, $idsB);
        $this->assertNotContains($prodA->id, $idsB);
    }

    public function test_create_order_with_bulk_products_and_variants_from_selected_site(): void
    {
        $prod1 = Product::create([
            'name' => 'Клавиатура Magic',
            'sku' => 'KEY-MAG',
            'price' => 150.00,
            'cost_price' => 90.00,
            'site_id' => $this->siteA->id,
            'is_active' => true,
        ]);

        $prod2 = Product::create([
            'name' => 'Футболка Polo',
            'sku' => 'POLO-BASE',
            'price' => 80.00,
            'cost_price' => 40.00,
            'site_id' => $this->siteA->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $prod2->id,
            'title' => 'Синий / L',
            'sku' => 'POLO-BLU-L',
            'price' => 85.00,
            'purchase_price' => 42.00,
            'is_active' => true,
        ]);

        $payload = [
            'site_id' => $this->siteA->id,
            'client_name' => 'Иван Иванов',
            'client_phone' => '+375 29 111-22-33',
            'client_address' => 'Минск, пр. Победителей 1',
            'order_items' => [
                [
                    'product_id' => $prod1->id,
                    'name' => $prod1->name,
                    'sku' => $prod1->sku,
                    'qty' => 2,
                    'price' => 150.00,
                    'purchase_price' => 90.00,
                ],
                [
                    'product_id' => $prod2->id,
                    'product_variant_id' => $variant->id,
                    'variant_title' => $variant->title,
                    'name' => $prod2->name,
                    'sku' => $variant->sku,
                    'qty' => 1,
                    'price' => 85.00,
                    'purchase_price' => 42.00,
                ],
            ],
            'total_price' => 385.00,
            'purchase_price' => 222.00,
        ];

        $response = $this->actingAs($this->manager)
            ->postJson('/api/orders', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('orders', [
            'site_id' => $this->siteA->id,
            'total_price' => 385.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $prod1->id,
            'product_variant_id' => null,
            'sku' => 'KEY-MAG',
            'qty' => 2,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $prod2->id,
            'product_variant_id' => $variant->id,
            'variant_title' => 'Синий / L',
            'sku' => 'POLO-BLU-L',
            'qty' => 1,
        ]);
    }

    public function test_rejects_products_from_different_site(): void
    {
        $prodB = Product::create([
            'name' => 'Товар Сайта Б',
            'sku' => 'PROD-B-DIFF',
            'price' => 120.00,
            'site_id' => $this->siteB->id,
            'is_active' => true,
        ]);

        $payload = [
            'site_id' => $this->siteA->id,
            'client_name' => 'Иван Иванов',
            'client_phone' => '+375 29 111-22-33',
            'order_items' => [
                [
                    'product_id' => $prodB->id,
                    'name' => $prodB->name,
                    'qty' => 1,
                    'price' => 120.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->manager)
            ->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_items.0.product_id']);
    }

    public function test_rejects_product_variants_from_different_site(): void
    {
        $prodB = Product::create([
            'name' => 'Товар Сайта Б с вариантами',
            'sku' => 'PROD-B-VAR',
            'price' => 120.00,
            'site_id' => $this->siteB->id,
            'is_active' => true,
        ]);

        $varB = ProductVariant::create([
            'product_id' => $prodB->id,
            'title' => 'Красный',
            'sku' => 'VAR-B-RED',
            'price' => 125.00,
            'is_active' => true,
        ]);

        $payload = [
            'site_id' => $this->siteA->id,
            'client_name' => 'Иван Иванов',
            'client_phone' => '+375 29 111-22-33',
            'order_items' => [
                [
                    'product_id' => $prodB->id,
                    'product_variant_id' => $varB->id,
                    'name' => $prodB->name,
                    'qty' => 1,
                    'price' => 125.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->manager)
            ->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_items.0.product_variant_id']);
    }

    public function test_active_filter_excludes_inactive_products_and_variants(): void
    {
        $inactiveProd = Product::create([
            'name' => 'Неактивный товар',
            'sku' => 'INACT-PROD',
            'price' => 100.00,
            'site_id' => $this->siteA->id,
            'is_active' => false,
        ]);

        $activeProd = Product::create([
            'name' => 'Активный товар',
            'sku' => 'ACT-PROD',
            'price' => 100.00,
            'site_id' => $this->siteA->id,
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $activeProd->id,
            'title' => 'Активный вариант',
            'sku' => 'ACT-VAR',
            'price' => 100.00,
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $activeProd->id,
            'title' => 'Архивный вариант',
            'sku' => 'ARCH-VAR',
            'price' => 100.00,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/products?site_id={$this->siteA->id}&is_active=true");

        $response->assertOk();
        $products = collect($response->json('products'));
        $this->assertFalse($products->contains('id', $inactiveProd->id));
        $this->assertTrue($products->contains('id', $activeProd->id));

        $foundActive = $products->firstWhere('id', $activeProd->id);
        $this->assertCount(1, $foundActive['variants']);
        $this->assertEquals('ACT-VAR', $foundActive['variants'][0]['sku']);
    }

    public function test_tma_order_create_view_renders_catalog_button_and_modal(): void
    {
        $response = $this->actingAs($this->manager)
            ->get('/tma/new');

        $response->assertOk()
            ->assertSee('Из каталога')
            ->assertSee('Выбор товаров из каталога')
            ->assertSee('showProductModal');
    }

    public function test_product_search_respects_limit_parameter(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            Product::create([
                'name' => "Товар {$i}",
                'sku' => "PROD-LIMIT-{$i}",
                'price' => 50.00 + $i,
                'site_id' => $this->siteA->id,
                'is_active' => true,
            ]);
        }

        $resLimit = $this->actingAs($this->manager)
            ->getJson("/api/products?site_id={$this->siteA->id}&limit=5");

        $resLimit->assertOk()
            ->assertJsonCount(5, 'products')
            ->assertJsonPath('pagination.per_page', 5);

        $resPerPage = $this->actingAs($this->manager)
            ->getJson("/api/products?site_id={$this->siteA->id}&per_page=5");

        $resPerPage->assertOk()
            ->assertJsonCount(5, 'products')
            ->assertJsonPath('pagination.per_page', 5);
    }

    public function test_tma_views_render_dropdown_product_search(): void
    {
        $order = \App\Models\Order::create([
            'site_id' => $this->siteA->id,
            'manager_id' => $this->manager->id,
            'status' => \App\Enums\OrderStatus::CREATED,
            'total_price' => 100,
            'purchase_price' => 50,
        ]);

        $resCreate = $this->actingAs($this->manager)->get('/tma/new');
        $resCreate->assertOk()
            ->assertSee('openQuickProductDropdown')
            ->assertSee('openItemProductDropdown')
            ->assertSee('Добавить товар из базы (справочника)...');

        $resEdit = $this->actingAs($this->manager)->get("/tma/orders/{$order->id}/edit");
        $resEdit->assertOk()
            ->assertSee('openQuickProductDropdown')
            ->assertSee('openItemProductDropdown')
            ->assertSee('Добавить товар из базы (справочника)...');

        $resShow = $this->actingAs($this->manager)->get("/tma/orders/{$order->id}");
        $resShow->assertOk()
            ->assertSee('openQuickProductDropdown')
            ->assertSee('openItemProductDropdown')
            ->assertSee('Добавить товар из базы (справочника)...');
    }
}
