<?php

namespace App\Services\Orders;

use App\Enums\ActionType;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Events\OrderAssigned;
use App\Events\OrderStatusChanged;
use App\Jobs\DeleteOrderChatMessagesJob;
use App\Jobs\NotifyCourierJob;
use App\Models\ActionLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderAssigner
{
    public function __construct(
        protected ActionLogger $actionLogger
    ) {}

    public function assignCourier(int $orderId, int $courierId, int $actorId, ?User $courier = null, bool $allowReassign = true): Order
    {
        return DB::transaction(function () use ($orderId, $courierId, $actorId, $courier, $allowReassign) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $courier = $courier ?? User::findOrFail($courierId);

            if (! $courier->isCourier()) {
                throw new \InvalidArgumentException('User is not a courier');
            }

            if ($order->manager_id === null) {
                throw new \InvalidArgumentException('Нельзя назначить курьера на заказ без назначенного менеджера');
            }

            $delivery = $order->delivery()->lockForUpdate()->first() ?? $order->delivery()->create([
                'type' => DeliveryType::COURIER->value,
            ]);

            if (! $allowReassign && $delivery->user_id !== null && $delivery->user_id !== $courier->id) {
                throw new \InvalidArgumentException('Заказ уже назначен другому курьеру');
            }

            $oldCourierId = $delivery->user_id;
            $delivery->user_id = $courierId;
            $delivery->save();

            $order->skipAuditLog = true;
            $order->update([
                'status' => OrderStatus::ASSIGNED,
                'courier_id' => $courierId,
            ]);

            $this->actionLogger->logAssign($order, $courier, $actorId);

            DB::afterCommit(function () use ($order, $courier, $oldCourierId, $courierId) {
                event(new OrderStatusChanged($order));

                if ($oldCourierId == $courierId) {
                    Log::info('Courier notification skipped: same courier reassigned', [
                        'order_id' => $order->id,
                        'courier_id' => $courierId,
                    ]);
                } elseif (! $courier->telegram_user_id) {
                    Log::warning('Courier notification skipped: missing telegram_user_id', [
                        'order_id' => $order->id,
                        'courier_id' => $courierId,
                    ]);
                } else {
                    event(new OrderAssigned($order->fresh(), (string) $courier->telegram_user_id));
                    NotifyCourierJob::dispatch($order->fresh(), $courier);
                }
            });

            return $order;
        });
    }

    public function acceptOrder(int $orderId, int $managerId, ?User $manager = null): Order
    {
        return DB::transaction(function () use ($orderId, $managerId, $manager) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $manager = $manager ?? User::findOrFail($managerId);

            if (! $manager->isManager() && ! $manager->isOwner()) {
                throw new \InvalidArgumentException('Only managers and owners can accept orders');
            }

            if ($order->manager_id !== null) {
                if ($order->manager_id === $manager->id) {
                    throw new \InvalidArgumentException('Вы уже приняли этот заказ');
                }
                throw new \InvalidArgumentException('Заказ уже принят другим менеджером');
            }

            $order->skipAuditLog = true;
            $order->update(['manager_id' => $managerId, 'status' => OrderStatus::ACCEPTED]);

            ActionLog::create([
                'actor_id' => $managerId,
                'order_id' => $order->id,
                'action_type' => ActionType::ASSIGN,
                'field_name' => 'manager_id',
                'old_value' => '',
                'new_value' => (string) $managerId,
                'created_at' => now(),
            ]);

            event(new OrderStatusChanged($order));

            DeleteOrderChatMessagesJob::dispatch($order->id)->afterCommit();

            return $order;
        });
    }

    public function rejectCourier(int $orderId, int $courierId, ?User $courier = null): Order
    {
        return DB::transaction(function () use ($orderId, $courierId, $courier) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $courier = $courier ?? User::findOrFail($courierId);

            if (! $courier->isCourier()) {
                throw new \InvalidArgumentException('Ошибка: вы не курьер');
            }

            $delivery = $order->delivery()->lockForUpdate()->first();
            if ($delivery && $delivery->user_id === $courier->id) {
                $delivery->update(['user_id' => null]);

                $order->skipAuditLog = true;
                $orderUpdates = ['courier_id' => null];
                if ($order->status === OrderStatus::ASSIGNED) {
                    $orderUpdates['status'] = OrderStatus::ACCEPTED;
                }
                $order->update($orderUpdates);

                ActionLog::create([
                    'actor_id' => $courier->id,
                    'order_id' => $order->id,
                    'action_type' => ActionType::UPDATE,
                    'field_name' => 'delivery.user_id',
                    'old_value' => (string) $courier->id,
                    'new_value' => '',
                    'created_at' => now(),
                ]);

                event(new OrderStatusChanged($order));
            }

            return $order;
        });
    }
}
