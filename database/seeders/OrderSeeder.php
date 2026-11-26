<?php

namespace Database\Seeders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class OrderSeeder extends Seeder
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

        $manager1 = User::firstOrCreate(
            ['telegram_user_id' => '111111'],
            [
                'role' => UserRole::MANAGER,
                'system_name' => 'Иванов Иван',
                'is_active' => true,
            ]
        );

        $manager2 = User::firstOrCreate(
            ['telegram_user_id' => '222222'],
            [
                'role' => UserRole::MANAGER,
                'system_name' => 'Петрова Анна',
                'is_active' => true,
            ]
        );

        $courier1 = User::firstOrCreate(
            ['telegram_user_id' => '333333'],
            [
                'role' => UserRole::COURIER,
                'system_name' => 'Сидоров Алексей',
                'is_active' => true,
            ]
        );

        $courier2 = User::firstOrCreate(
            ['telegram_user_id' => '444444'],
            [
                'role' => UserRole::COURIER,
                'system_name' => 'Кузнецов Дмитрий',
                'is_active' => true,
            ]
        );

        $managers = [$manager1, $manager2];
        $couriers = [$courier1, $courier2];

        $clients = [
            ['name' => 'Алексей С.', 'phone' => '+7 (999) 111-11-11', 'address' => 'г. Москва, ул. Ленина, д. 10'],
            ['name' => 'Мария К.', 'phone' => '+7 (999) 222-22-22', 'address' => 'г. Москва, пр. Мира, д. 25'],
            ['name' => 'Дмитрий В.', 'phone' => '+7 (999) 333-33-33', 'address' => 'г. Москва, ул. Пушкина, д. 5'],
            ['name' => 'Елена Н.', 'phone' => '+7 (999) 444-44-44', 'address' => 'г. Санкт-Петербург, Невский пр., д. 100'],
            ['name' => 'Сергей П.', 'phone' => '+7 (999) 555-55-55', 'address' => 'г. Екатеринбург, ул. Мира, д. 50'],
            ['name' => 'Ольга М.', 'phone' => '+7 (999) 666-66-66', 'address' => 'г. Новосибирск, ул. Красная, д. 20'],
            ['name' => 'Андрей Г.', 'phone' => '+7 (999) 777-77-77', 'address' => 'г. Казань, ул. Баумана, д. 15'],
            ['name' => 'Наталья Ф.', 'phone' => '+7 (999) 888-88-88', 'address' => 'г. Краснодар, ул. Красная, д. 30'],
        ];

        $productSets = [
            [
                ['name' => 'iPhone 15', 'qty' => 1, 'price' => 80000],
                ['name' => 'Чехол iPhone 15', 'qty' => 1, 'price' => 2000],
            ],
            [
                ['name' => 'MacBook Air M2', 'qty' => 1, 'price' => 120000],
            ],
            [
                ['name' => 'AirPods Pro 2', 'qty' => 2, 'price' => 25000],
                ['name' => 'Зарядное устройство USB-C', 'qty' => 1, 'price' => 3000],
            ],
            [
                ['name' => 'iPad 10.9"', 'qty' => 1, 'price' => 45000],
                ['name' => 'Apple Pencil', 'qty' => 1, 'price' => 12000],
                ['name' => 'Чехол iPad', 'qty' => 1, 'price' => 4000],
            ],
            [
                ['name' => 'Samsung Galaxy S24', 'qty' => 1, 'price' => 70000],
            ],
            [
                ['name' => 'Xiaomi 14 Pro', 'qty' => 1, 'price' => 60000],
                ['name' => 'Беспроводное ЗУ Xiaomi', 'qty' => 1, 'price' => 2500],
            ],
            [
                ['name' => 'Sony WH-1000XM5', 'qty' => 1, 'price' => 30000],
            ],
            [
                ['name' => 'Холодильник Samsung', 'qty' => 1, 'price' => 45000],
                ['name' => 'Микроволновка', 'qty' => 1, 'price' => 15000],
            ],
            [
                ['name' => 'Телевизор LG 55"', 'qty' => 1, 'price' => 65000],
            ],
        ];

        $statuses = [
            OrderStatus::CREATED,
            OrderStatus::ASSIGNED,
            OrderStatus::AT_WAREHOUSE,
            OrderStatus::COMPLETED,
        ];

        $catalogProducts = Product::with('variants')->get();

        foreach (range(1, 20) as $i) {
            $client = Arr::random($clients);
            $manager = Arr::random($managers);
            $courier = Arr::random($couriers);
            $status = Arr::random($statuses);

            $productSet = Arr::random($productSets);
            $orderItems = [];
            $totalPrice = 0;
            $calculatedPurchasePrice = 0;

            foreach ($productSet as $product) {
                $matched = $catalogProducts->first(function ($p) use ($product) {
                    return str_contains(mb_strtolower($p->name), mb_strtolower($product['name']))
                        || str_contains(mb_strtolower($product['name']), mb_strtolower($p->name));
                });

                if ($matched) {
                    $variant = $matched->variants->isNotEmpty() ? $matched->variants->random() : null;
                    $itemPrice = $variant && $variant->price ? (float) $variant->price : (float) $matched->price;
                    $itemPurchase = $variant && $variant->purchase_price ? (float) $variant->purchase_price : (float) $matched->cost_price;

                    $orderItems[] = [
                        'product_id' => $matched->id,
                        'product_variant_id' => $variant?->id,
                        'external_product_id' => $matched->external_id,
                        'external_variant_id' => $variant?->external_id,
                        'name' => $matched->name,
                        'variant_title' => $variant?->title,
                        'sku' => $variant?->sku ?? $matched->sku,
                        'qty' => $product['qty'],
                        'price' => $itemPrice,
                        'purchase_price' => $itemPurchase,
                    ];

                    $totalPrice += $itemPrice * $product['qty'];
                    $calculatedPurchasePrice += $itemPurchase * $product['qty'];
                } else {
                    $orderItems[] = [
                        'name' => $product['name'],
                        'qty' => $product['qty'],
                        'price' => $product['price'],
                        'purchase_price' => round($product['price'] * 0.7, 2),
                    ];
                    $totalPrice += $product['price'] * $product['qty'];
                    $calculatedPurchasePrice += round($product['price'] * 0.7, 2) * $product['qty'];
                }
            }

            $purchasePrice = $calculatedPurchasePrice > 0 ? $calculatedPurchasePrice : round($totalPrice * 0.7, 2);

            $normalizedPhone = PhoneNumber::normalize($client['phone']);
            $customer = Customer::firstOrCreate(
                ['site_id' => $site->id, 'phone' => $normalizedPhone],
                [
                    'name' => $client['name'],
                    'address' => $client['address'],
                ]
            );

            $hasDelivery = $status === OrderStatus::COMPLETED || $status === OrderStatus::AT_WAREHOUSE || $status === OrderStatus::ASSIGNED;

            Order::withoutEvents(function () use (
                $site,
                $manager,
                $customer,
                $orderItems,
                $totalPrice,
                $purchasePrice,
                $status,
                $hasDelivery,
                $courier,
                $client
            ) {
                $order = Order::create([
                    'site_id' => $site->id,
                    'manager_id' => $manager->id,
                    'customer_id' => $customer->id,
                    'total_price' => $totalPrice,
                    'purchase_price' => $purchasePrice,
                    'status' => $status,
                    'is_edited' => rand(0, 1) === 1,
                    'extra_data' => [
                        'source' => 'seeder',
                        'note' => 'Demo order',
                    ],
                ]);

                foreach ($orderItems as $item) {
                    $order->items()->create($item);
                }

                if ($hasDelivery) {
                    $order->delivery()->create([
                        'type' => DeliveryType::COURIER,
                        'user_id' => $courier->id,
                        'delivery_address' => $client['address'],
                    ]);
                }

                $order->payment()->create([
                    'method' => PaymentMethod::CASH,
                    'status' => PaymentStatus::PAID,
                    'amount' => $totalPrice,
                ]);
            });
        }

        $this->command->info('Seeded 20 orders successfully.');
    }
}
