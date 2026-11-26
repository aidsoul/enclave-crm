<?php

namespace Database\Seeders;

use App\Enums\ActionType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\ActionLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditSeeder extends Seeder
{
    public function run(): void
    {
        $orders = Order::all();

        if ($orders->isEmpty()) {
            $this->command->warn('No orders found for audit seeding.');

            return;
        }

        $actors = User::byRole(UserRole::MANAGER)->active()->get();
        $owners = User::byRole(UserRole::OWNER)->active()->get();
        $allActors = $actors->merge($owners);

        if ($allActors->isEmpty()) {
            $this->command->warn('No managers or owners found for audit seeding.');

            return;
        }

        $prevStatusMap = [
            OrderStatus::CREATED->value => null,
            OrderStatus::ASSIGNED->value => OrderStatus::CREATED->value,
            OrderStatus::AT_WAREHOUSE->value => OrderStatus::ASSIGNED->value,
            OrderStatus::COMPLETED->value => OrderStatus::AT_WAREHOUSE->value,
        ];

        foreach ($orders as $order) {
            $actor = $allActors->random();

            ActionLog::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'action_type' => ActionType::CREATE,
                    'field_name' => null,
                    'created_at' => $order->created_at,
                ],
                [
                    'actor_id' => $actor->id,
                    'old_value' => null,
                    'new_value' => json_encode($order->order_content),
                ]
            );

            if ($order->is_edited) {
                ActionLog::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'action_type' => ActionType::UPDATE,
                        'field_name' => 'total_price',
                        'created_at' => $order->created_at->addMinutes(5),
                    ],
                    [
                        'actor_id' => $actor->id,
                        'old_value' => '1000.00',
                        'new_value' => (string) $order->total_price,
                    ]
                );
            }

            $prevStatus = $prevStatusMap[$order->status->value] ?? null;

            if ($prevStatus) {
                ActionLog::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'action_type' => ActionType::STATUS_CHANGE,
                        'field_name' => 'status',
                        'created_at' => $order->created_at->addMinutes(10),
                    ],
                    [
                        'actor_id' => $actor->id,
                        'old_value' => $prevStatus,
                        'new_value' => $order->status->value,
                    ]
                );
            }

            if ($order->courier_id) {
                ActionLog::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'action_type' => ActionType::ASSIGN,
                        'field_name' => 'courier_id',
                        'created_at' => $order->created_at->addMinutes(15),
                    ],
                    [
                        'actor_id' => $actor->id,
                        'old_value' => '',
                        'new_value' => (string) $order->courier_id,
                    ]
                );
            }

            if (rand(1, 10) <= 2) {
                ActionLog::create([
                    'actor_id' => $actor->id,
                    'order_id' => $order->id,
                    'action_type' => ActionType::PANIC,
                    'field_name' => null,
                    'old_value' => null,
                    'new_value' => 'Клиент не отвечает на звонки',
                    'created_at' => $order->created_at->addHours(rand(1, 24)),
                ]);
            }
        }

        $this->command->info('Seeded audit logs for '.$orders->count().' orders.');
    }
}
