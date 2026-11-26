<?php

namespace Tests\Feature\Catalog;

use App\Jobs\SyncCatalogJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogSyncTest extends TestCase
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

    public function test_catalog_sync_requires_valid_api_token(): void
    {
        $response = $this->postJson('/api/catalog/sync', [
            'products' => [
                ['external_id' => 'p1', 'name' => 'Товар 1'],
            ],
        ]);

        $response->assertStatus(401);

        $responseInvalid = $this->withHeaders(['X-API-Token' => 'invalid-token'])
            ->postJson('/api/catalog/sync', [
                'products' => [
                    ['external_id' => 'p1', 'name' => 'Товар 1'],
                ],
            ]);

        $responseInvalid->assertStatus(403);
    }

    public function test_catalog_sync_synchronously_creates_products_and_variants(): void
    {
        $site = $this->createSite();

        $payload = [
            'products' => [
                [
                    'external_id' => 'p-100',
                    'name' => 'Худи Oversize',
                    'sku' => 'HUDI-MAIN',
                    'description' => 'Теплое худи',
                    'image_url' => 'https://example.com/hudi.jpg',
                    'url' => 'https://example.com/products/hudi',
                    'price' => 2500.00,
                    'cost_price' => 1200.00,
                    'is_active' => true,
                    'variants' => [
                        [
                            'external_id' => 'v-100-1',
                            'sku' => 'HUDI-BLK-M',
                            'title' => 'Черный / M',
                            'attributes' => ['color' => 'Черный', 'size' => 'M'],
                            'price' => 2500.00,
                            'purchase_price' => 1200.00,
                            'is_active' => true,
                        ],
                        [
                            'external_id' => 'v-100-2',
                            'sku' => 'HUDI-BLK-L',
                            'title' => 'Черный / L',
                            'attributes' => ['color' => 'Черный', 'size' => 'L'],
                            'price' => 2600.00,
                            'purchase_price' => 1250.00,
                            'is_active' => true,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->withHeaders(['X-API-Token' => $site->api_token])
            ->postJson('/api/catalog/sync', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'synced_products' => 1,
                'synced_variants' => 2,
            ]);

        $this->assertDatabaseHas('products', [
            'site_id' => $site->id,
            'external_id' => 'p-100',
            'name' => 'Худи Oversize',
            'image_url' => 'https://example.com/hudi.jpg',
        ]);

        $product = Product::where('external_id', 'p-100')->first();
        $this->assertNotNull($product);
        $this->assertCount(2, $product->variants);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'external_id' => 'v-100-1',
            'sku' => 'HUDI-BLK-M',
            'title' => 'Черный / M',
        ]);
    }

    public function test_catalog_sync_updates_existing_records_without_duplicates(): void
    {
        $site = $this->createSite();

        $product = Product::create([
            'site_id' => $site->id,
            'external_id' => 'p-200',
            'name' => 'Старое название',
            'price' => 1000,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'external_id' => 'v-200-1',
            'title' => 'Старый вариант',
            'price' => 1000,
        ]);

        $payload = [
            'products' => [
                [
                    'external_id' => 'p-200',
                    'name' => 'Новое название',
                    'price' => 1500,
                    'variants' => [
                        [
                            'external_id' => 'v-200-1',
                            'title' => 'Обновленный вариант',
                            'price' => 1500,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->withHeaders(['X-API-Token' => $site->api_token])
            ->postJson('/api/catalog/sync', $payload);

        $response->assertStatus(200);

        $this->assertEquals(1, Product::where('site_id', $site->id)->where('external_id', 'p-200')->count());
        $this->assertEquals(1, ProductVariant::where('product_id', $product->id)->where('external_id', 'v-200-1')->count());

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Новое название',
            'price' => 1500,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'external_id' => 'v-200-1',
            'title' => 'Обновленный вариант',
            'price' => 1500,
        ]);
    }

    public function test_catalog_sync_can_be_dispatched_asynchronously(): void
    {
        Queue::fake();

        $site = $this->createSite();

        $payload = [
            'async' => true,
            'products' => [
                [
                    'external_id' => 'p-300',
                    'name' => 'Асинхронный товар',
                ],
            ],
        ];

        $response = $this->withHeaders(['X-API-Token' => $site->api_token])
            ->postJson('/api/catalog/sync', $payload);

        $response->assertStatus(202)
            ->assertJsonStructure(['message', 'job_id']);

        Queue::assertPushed(SyncCatalogJob::class, function ($job) use ($site) {
            return $job->siteId === $site->id;
        });
    }
}
