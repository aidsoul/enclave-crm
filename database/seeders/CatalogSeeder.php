<?php

namespace Database\Seeders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::firstOrCreate(
            ['domain' => 'test.com'],
            [
                'name' => 'test.com',
                'is_active' => true,
            ]
        );

        $catalog = [
            [
                'external_id' => 'prod-apple-iphone-15-pro',
                'sku' => 'IPH-15P',
                'name' => 'Смартфон Apple iPhone 15 Pro',
                'description' => 'Флагманский смартфон в титановом корпусе с чипом A17 Pro, кнопкой действия и системой камер Pro 48 Мп.',
                'image_url' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=600&auto=format&fit=crop&q=80',
                'url' => 'https://apple.com/iphone-15-pro',
                'price' => 3990.00,
                'cost_price' => 3350.00,
                'variants' => [
                    [
                        'external_id' => 'var-iph-15p-128-nat',
                        'sku' => 'IPH-15P-128-NAT',
                        'title' => '128 ГБ / Natural Titanium',
                        'price' => 3990.00,
                        'purchase_price' => 3350.00,
                        'attributes' => ['storage' => '128 ГБ', 'color' => 'Natural Titanium'],
                    ],
                    [
                        'external_id' => 'var-iph-15p-256-blk',
                        'sku' => 'IPH-15P-256-BLK',
                        'title' => '256 ГБ / Black Titanium',
                        'price' => 4390.00,
                        'purchase_price' => 3690.00,
                        'attributes' => ['storage' => '256 ГБ', 'color' => 'Black Titanium'],
                    ],
                    [
                        'external_id' => 'var-iph-15p-512-blu',
                        'sku' => 'IPH-15P-512-BLU',
                        'title' => '512 ГБ / Blue Titanium',
                        'price' => 4990.00,
                        'purchase_price' => 4190.00,
                        'attributes' => ['storage' => '512 ГБ', 'color' => 'Blue Titanium'],
                    ],
                ],
            ],
            [
                'external_id' => 'prod-apple-macbook-air-15',
                'sku' => 'MBA-15-M2',
                'name' => 'Ноутбук Apple MacBook Air 15" M2',
                'description' => 'Невероятно тонкий 15-дюймовый ноутбук на базе мощного энергоэффективного процессора M2.',
                'image_url' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&auto=format&fit=crop&q=80',
                'url' => 'https://apple.com/macbook-air-15',
                'price' => 4850.00,
                'cost_price' => 4100.00,
                'variants' => [
                    [
                        'external_id' => 'var-mba-15-8-256-mid',
                        'sku' => 'MBA-15-8-256-MID',
                        'title' => '8 ГБ / 256 ГБ / Midnight',
                        'price' => 4850.00,
                        'purchase_price' => 4100.00,
                        'attributes' => ['ram' => '8 ГБ', 'ssd' => '256 ГБ', 'color' => 'Midnight'],
                    ],
                    [
                        'external_id' => 'var-mba-15-16-512-gry',
                        'sku' => 'MBA-15-16-512-GRY',
                        'title' => '16 ГБ / 512 ГБ / Space Gray',
                        'price' => 5650.00,
                        'purchase_price' => 4750.00,
                        'attributes' => ['ram' => '16 ГБ', 'ssd' => '512 ГБ', 'color' => 'Space Gray'],
                    ],
                ],
            ],
            [
                'external_id' => 'prod-apple-airpods-pro-2',
                'sku' => 'APP-2-USBC',
                'name' => 'Беспроводные наушники Apple AirPods Pro 2 (USB-C)',
                'description' => 'Активное шумоподавление до 2 раз эффективнее, режим прозрачности и пространственное аудио.',
                'image_url' => 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?w=600&auto=format&fit=crop&q=80',
                'url' => 'https://apple.com/airpods-pro',
                'price' => 890.00,
                'cost_price' => 690.00,
                'variants' => [
                    [
                        'external_id' => 'var-app-2-wht',
                        'sku' => 'APP-2-WHT',
                        'title' => 'White / MagSafe Case',
                        'price' => 890.00,
                        'purchase_price' => 690.00,
                        'attributes' => ['color' => 'White'],
                    ],
                ],
            ],
            [
                'external_id' => 'prod-samsung-galaxy-watch-6',
                'sku' => 'GW6-44',
                'name' => 'Смарт-часы Samsung Galaxy Watch 6',
                'description' => 'Яркий дисплей Super AMOLED, сапфировое стекло, подробный анализ сна и датчики здоровья.',
                'image_url' => 'https://images.unsplash.com/photo-1579586337278-3befd40fd17a?w=600&auto=format&fit=crop&q=80',
                'url' => 'https://samsung.com/galaxy-watch6',
                'price' => 990.00,
                'cost_price' => 760.00,
                'variants' => [
                    [
                        'external_id' => 'var-gw6-40-gra',
                        'sku' => 'GW6-40-GRA',
                        'title' => '40 мм / Graphite',
                        'price' => 890.00,
                        'purchase_price' => 690.00,
                        'attributes' => ['case_size' => '40 мм', 'color' => 'Graphite'],
                    ],
                    [
                        'external_id' => 'var-gw6-44-slv',
                        'sku' => 'GW6-44-SLV',
                        'title' => '44 мм / Silver',
                        'price' => 990.00,
                        'purchase_price' => 760.00,
                        'attributes' => ['case_size' => '44 мм', 'color' => 'Silver'],
                    ],
                ],
            ],
            [
                'external_id' => 'prod-glass-25d',
                'sku' => 'ACC-GLS-15',
                'name' => 'Защитное стекло 2.5D Premium',
                'description' => 'Ударопрочное закаленное стекло с олеофобным покрытием и закругленными гранями 2.5D.',
                'image_url' => 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?w=600&auto=format&fit=crop&q=80',
                'url' => 'https://accessories.example.com/glass-25d',
                'price' => 35.00,
                'cost_price' => 12.00,
                'variants' => [],
            ],
            [
                'external_id' => 'prod-silicone-case-magsafe',
                'sku' => 'ACC-MS-CASE',
                'name' => 'Чехол Silicone Case with MagSafe',
                'description' => 'Шелковистый силикон со встроенными магнитами MagSafe для быстрой беспроводной зарядки.',
                'image_url' => 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=600&auto=format&fit=crop&q=80',
                'url' => 'https://accessories.example.com/silicone-case',
                'price' => 65.00,
                'cost_price' => 24.00,
                'variants' => [
                    [
                        'external_id' => 'var-case-blk',
                        'sku' => 'ACC-MS-BLK',
                        'title' => 'Black / Черный',
                        'price' => 65.00,
                        'purchase_price' => 24.00,
                        'attributes' => ['color' => 'Черный'],
                    ],
                    [
                        'external_id' => 'var-case-blu',
                        'sku' => 'ACC-MS-BLU',
                        'title' => 'Storm Blue / Темно-синий',
                        'price' => 65.00,
                        'purchase_price' => 24.00,
                        'attributes' => ['color' => 'Темно-синий'],
                    ],
                ],
            ],
        ];

        $savedProducts = [];

        foreach ($catalog as $item) {
            $product = Product::updateOrCreate(
                [
                    'site_id' => $site->id,
                    'external_id' => $item['external_id'],
                ],
                [
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'image_url' => $item['image_url'],
                    'url' => $item['url'],
                    'price' => $item['price'],
                    'cost_price' => $item['cost_price'],
                    'is_active' => true,
                ]
            );

            $savedProducts[$item['external_id']] = [
                'product' => $product,
                'variants' => [],
            ];

            foreach ($item['variants'] as $variantData) {
                $variant = ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'external_id' => $variantData['external_id'],
                    ],
                    [
                        'sku' => $variantData['sku'],
                        'title' => $variantData['title'],
                        'price' => $variantData['price'],
                        'purchase_price' => $variantData['purchase_price'],
                        'attributes' => $variantData['attributes'],
                        'is_active' => true,
                    ]
                );

                $savedProducts[$item['external_id']]['variants'][] = $variant;
            }
        }

        $this->command->info('Seeded '.count($savedProducts).' catalog products with variants.');

        $this->linkExistingOrderItems($site, $savedProducts);
        $this->seedShowcaseOrders($site, $savedProducts);
    }

    /**
     * @param  array<string, array{product: Product, variants: array<ProductVariant>}>  $savedProducts
     */
    private function linkExistingOrderItems(Site $site, array $savedProducts): void
    {
        $orderItems = OrderItem::whereNull('product_id')->get();
        $updatedCount = 0;

        $iphoneData = $savedProducts['prod-apple-iphone-15-pro'] ?? null;
        $macbookData = $savedProducts['prod-apple-macbook-air-15'] ?? null;
        $airpodsData = $savedProducts['prod-apple-airpods-pro-2'] ?? null;
        $glassData = $savedProducts['prod-glass-25d'] ?? null;
        $caseData = $savedProducts['prod-silicone-case-magsafe'] ?? null;

        foreach ($orderItems as $item) {
            $nameLower = mb_strtolower($item->name);

            if (str_contains($nameLower, 'iphone') && $iphoneData) {
                $variant = $iphoneData['variants'][0] ?? null;
                $item->update([
                    'product_id' => $iphoneData['product']->id,
                    'product_variant_id' => $variant?->id,
                    'external_product_id' => $iphoneData['product']->external_id,
                    'external_variant_id' => $variant?->external_id,
                    'variant_title' => $variant?->title,
                    'sku' => $variant?->sku ?? $iphoneData['product']->sku,
                    'purchase_price' => $item->purchase_price ?: ($variant?->purchase_price ?? $iphoneData['product']->cost_price),
                ]);
                $updatedCount++;
            } elseif (str_contains($nameLower, 'macbook') && $macbookData) {
                $variant = $macbookData['variants'][0] ?? null;
                $item->update([
                    'product_id' => $macbookData['product']->id,
                    'product_variant_id' => $variant?->id,
                    'external_product_id' => $macbookData['product']->external_id,
                    'external_variant_id' => $variant?->external_id,
                    'variant_title' => $variant?->title,
                    'sku' => $variant?->sku ?? $macbookData['product']->sku,
                    'purchase_price' => $item->purchase_price ?: ($variant?->purchase_price ?? $macbookData['product']->cost_price),
                ]);
                $updatedCount++;
            } elseif (str_contains($nameLower, 'airpods') && $airpodsData) {
                $variant = $airpodsData['variants'][0] ?? null;
                $item->update([
                    'product_id' => $airpodsData['product']->id,
                    'product_variant_id' => $variant?->id,
                    'external_product_id' => $airpodsData['product']->external_id,
                    'external_variant_id' => $variant?->external_id,
                    'variant_title' => $variant?->title,
                    'sku' => $variant?->sku ?? $airpodsData['product']->sku,
                    'purchase_price' => $item->purchase_price ?: ($variant?->purchase_price ?? $airpodsData['product']->cost_price),
                ]);
                $updatedCount++;
            } elseif (str_contains($nameLower, 'стекло') && $glassData) {
                $item->update([
                    'product_id' => $glassData['product']->id,
                    'external_product_id' => $glassData['product']->external_id,
                    'sku' => $glassData['product']->sku,
                    'purchase_price' => $item->purchase_price ?: $glassData['product']->cost_price,
                ]);
                $updatedCount++;
            } elseif (str_contains($nameLower, 'чехол') && $caseData) {
                $variant = $caseData['variants'][0] ?? null;
                $item->update([
                    'product_id' => $caseData['product']->id,
                    'product_variant_id' => $variant?->id,
                    'external_product_id' => $caseData['product']->external_id,
                    'external_variant_id' => $variant?->external_id,
                    'variant_title' => $variant?->title,
                    'sku' => $variant?->sku ?? $caseData['product']->sku,
                    'purchase_price' => $item->purchase_price ?: ($variant?->purchase_price ?? $caseData['product']->cost_price),
                ]);
                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            $this->command->info("Linked {$updatedCount} existing order items to catalog products.");
        }
    }

    /**
     * @param  array<string, array{product: Product, variants: array<ProductVariant>}>  $savedProducts
     */
    private function seedShowcaseOrders(Site $site, array $savedProducts): void
    {
        $manager = User::firstOrCreate(
            ['telegram_user_id' => '111111'],
            [
                'role' => UserRole::MANAGER,
                'system_name' => 'Иванов Иван',
                'is_active' => true,
            ]
        );

        $courier = User::firstOrCreate(
            ['telegram_user_id' => '333333'],
            [
                'role' => UserRole::COURIER,
                'system_name' => 'Сидоров Алексей',
                'is_active' => true,
            ]
        );

        $customer = Customer::firstOrCreate(
            ['site_id' => $site->id, 'phone' => PhoneNumber::normalize('+375 (29) 123-45-67')],
            [
                'name' => 'Максим Соколов',
                'address' => 'г. Минск, пр. Победителей, д. 20, кв. 45',
            ]
        );

        $iphoneData = $savedProducts['prod-apple-iphone-15-pro'];
        $caseData = $savedProducts['prod-silicone-case-magsafe'];
        $glassData = $savedProducts['prod-glass-25d'];

        $variantIphone = $iphoneData['variants'][1] ?? $iphoneData['variants'][0];
        $variantCase = $caseData['variants'][0];

        $items = [
            [
                'product_id' => $iphoneData['product']->id,
                'product_variant_id' => $variantIphone->id,
                'external_product_id' => $iphoneData['product']->external_id,
                'external_variant_id' => $variantIphone->external_id,
                'name' => $iphoneData['product']->name,
                'variant_title' => $variantIphone->title,
                'sku' => $variantIphone->sku,
                'qty' => 1,
                'price' => (float) $variantIphone->price,
                'purchase_price' => (float) $variantIphone->purchase_price,
            ],
            [
                'product_id' => $caseData['product']->id,
                'product_variant_id' => $variantCase->id,
                'external_product_id' => $caseData['product']->external_id,
                'external_variant_id' => $variantCase->external_id,
                'name' => $caseData['product']->name,
                'variant_title' => $variantCase->title,
                'sku' => $variantCase->sku,
                'qty' => 1,
                'price' => (float) $variantCase->price,
                'purchase_price' => (float) $variantCase->purchase_price,
            ],
            [
                'product_id' => $glassData['product']->id,
                'product_variant_id' => null,
                'external_product_id' => $glassData['product']->external_id,
                'external_variant_id' => null,
                'name' => $glassData['product']->name,
                'variant_title' => null,
                'sku' => $glassData['product']->sku,
                'qty' => 2,
                'price' => (float) $glassData['product']->price,
                'purchase_price' => (float) $glassData['product']->cost_price,
            ],
        ];

        $totalPrice = array_sum(array_map(fn ($it) => $it['price'] * $it['qty'], $items));
        $purchasePrice = array_sum(array_map(fn ($it) => $it['purchase_price'] * $it['qty'], $items));

        $order = Order::create([
            'site_id' => $site->id,
            'manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'total_price' => $totalPrice,
            'purchase_price' => $purchasePrice,
            'status' => OrderStatus::ASSIGNED,
            'is_edited' => false,
            'extra_data' => [
                'source' => 'catalog_showcase',
                'note' => 'Показательный заказ с модификациями и аксессуарами',
            ],
        ]);

        foreach ($items as $item) {
            $order->items()->create($item);
        }

        $order->delivery()->create([
            'type' => DeliveryType::COURIER,
            'user_id' => $courier->id,
            'delivery_address' => $customer->address,
        ]);

        $order->payment()->create([
            'method' => PaymentMethod::CASH,
            'status' => PaymentStatus::PENDING,
            'amount' => $totalPrice,
        ]);

        $singleCustomer = Customer::firstOrCreate(
            ['site_id' => $site->id, 'phone' => PhoneNumber::normalize('+375 (29) 987-65-43')],
            [
                'name' => 'Алина Ветрова',
                'address' => 'г. Минск, ул. Немига, д. 5, кв. 12',
            ]
        );

        $airpodsData = $savedProducts['prod-apple-airpods-pro-2'];
        $airpodsVariant = $airpodsData['variants'][0];

        $singleOrder = Order::create([
            'site_id' => $site->id,
            'manager_id' => $manager->id,
            'customer_id' => $singleCustomer->id,
            'total_price' => (float) $airpodsVariant->price,
            'purchase_price' => (float) $airpodsVariant->purchase_price,
            'status' => OrderStatus::CREATED,
            'is_edited' => false,
            'extra_data' => [
                'source' => 'catalog_showcase_single',
                'note' => 'Заказ с одним товаром (для проверки скрытия кнопки "Все товары")',
            ],
        ]);

        $singleOrder->items()->create([
            'product_id' => $airpodsData['product']->id,
            'product_variant_id' => $airpodsVariant->id,
            'external_product_id' => $airpodsData['product']->external_id,
            'external_variant_id' => $airpodsVariant->external_id,
            'name' => $airpodsData['product']->name,
            'variant_title' => $airpodsVariant->title,
            'sku' => $airpodsVariant->sku,
            'qty' => 1,
            'price' => (float) $airpodsVariant->price,
            'purchase_price' => (float) $airpodsVariant->purchase_price,
        ]);

        $singleOrder->delivery()->create([
            'type' => DeliveryType::COURIER,
            'delivery_address' => $singleCustomer->address,
        ]);

        $singleOrder->payment()->create([
            'method' => PaymentMethod::ERIP,
            'status' => PaymentStatus::PAID,
            'amount' => (float) $airpodsVariant->price,
        ]);

        $this->command->info("Created showcase orders #{$order->id} (multi-item) and #{$singleOrder->id} (single-item).");
    }
}
