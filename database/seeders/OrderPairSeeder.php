<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderPairSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('domain', 'pair-seed.local')->firstOrFail();

        $manager = User::firstOrCreate(
            ['telegram_user_id' => '111111'],
            [
                'role' => UserRole::MANAGER,
                'system_name' => 'Manager Pair',
                'is_active' => true,
            ]
        );

        $courier = User::firstOrCreate(
            ['telegram_user_id' => '222222'],
            [
                'role' => UserRole::COURIER,
                'system_name' => 'Courier Pair',
                'is_active' => true,
            ]
        );

        $phone = '+7 (999) 123-45-67';

        Order::withoutEvents(function () use ($site, $manager, $courier, $phone) {
            $order1 = Order::firstOrCreate(
                ['site_id' => $site->id, 'id' => 1001],
                [
                    'manager_id' => $manager->id,
                    'courier_id' => $courier->id,
                    'order_content' => [['name' => 'Pair Order 1', 'qty' => 1, 'price' => null]],
                    'total_price' => 1500.00,
                    'purchase_price' => 900.00,
                    'status' => OrderStatus::COMPLETED,
                    'is_edited' => false,
                ]
            );
            $order1->update([
                'client_data' => [
                    'name' => 'Клиент Парный',
                    'phone' => $phone,
                    'address' => 'г. Москва, ул. Примерная, д. 1',
                ],
            ]);

            $order2 = Order::firstOrCreate(
                ['site_id' => $site->id, 'id' => 1002],
                [
                    'manager_id' => $manager->id,
                    'courier_id' => $courier->id,
                    'order_content' => [['name' => 'Pair Order 2', 'qty' => 1, 'price' => null]],
                    'total_price' => 2200.50,
                    'purchase_price' => 1400.00,
                    'status' => OrderStatus::ASSIGNED,
                    'is_edited' => true,
                ]
            );
            $order2->update([
                'client_data' => [
                    'name' => 'Клиент Парный',
                    'phone' => $phone,
                    'address' => 'г. Москва, ул. Примерная, д. 1',
                ],
            ]);

            $this->command->info('Pair orders created/updated: '.$order1->id.', '.$order2->id);
        });
    }
}
