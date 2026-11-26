<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class NewOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('domain', 'seed.local')->firstOrFail();

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

        foreach (range(1, 10) as $i) {
            $client = Arr::random($clients);
            $productSet = Arr::random($productSets);

            $orderContent = [];
            foreach ($productSet as $product) {
                $orderContent[] = [
                    'name' => $product['name'],
                    'qty' => $product['qty'],
                    'price' => $product['price'],
                ];
            }

            $totalPrice = 0;
            foreach ($orderContent as $item) {
                $totalPrice += $item['price'] * $item['qty'];
            }
            $purchasePrice = round($totalPrice * (rand(60, 85) / 100), 2);

            Order::withoutEvents(function () use (
                $site,
                $client,
                $orderContent,
                $totalPrice,
                $purchasePrice
            ) {
                Order::create([
                    'site_id' => $site->id,
                    'manager_id' => null,
                    'courier_id' => null,
                    'client_data' => [
                        'name' => $client['name'],
                        'phone' => $client['phone'],
                        'address' => $client['address'],
                    ],
                    'order_content' => $orderContent,
                    'total_price' => $totalPrice,
                    'purchase_price' => $purchasePrice,
                    'status' => OrderStatus::CREATED,
                    'is_edited' => rand(0, 1) === 1,
                ]);
            });
        }

        $this->command->info('Seeded 10 new orders successfully.');
    }
}
