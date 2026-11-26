<?php

namespace App\Services\Orders;

use App\Enums\ActionType;
use App\Events\OrderStatusChanged;
use App\Models\ActionLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BulkOrderService
{
    public function __construct(
        protected OrderStatusMachine $orderStatusMachine,
        protected OrderAssigner $orderAssigner
    ) {}

    public function cancel(array $orderIds, User $user): array
    {
        $cancelledIds = [];
        $errors = [];
        $orders = Order::with('delivery')->whereIn('id', $orderIds)->get()->keyBy('id');

        foreach ($orderIds as $orderId) {
            $order = $orders->get($orderId);
            if (! $order) {
                continue;
            }

            if (! Gate::forUser($user)->allows('cancel', $order)) {
                $errors[$orderId] = 'Нет прав на отмену заказа';

                continue;
            }

            try {
                $this->orderStatusMachine->markCancelled($order->id, $user->id);
                $cancelledIds[] = $order->id;
            } catch (\Exception $e) {
                $errors[$orderId] = $e->getMessage();
            }
        }

        return [
            'success' => true,
            'count' => count($cancelledIds),
            'cancelled_ids' => $cancelledIds,
            'errors' => $errors,
        ];
    }

    public function assignCourier(array $orderIds, int $courierId, User $user): array
    {
        $courier = User::find($courierId);
        if (! $courier || ! $courier->isCourier() || ! $courier->isActive()) {
            return ['error' => 'User is not a courier', 'status' => 422];
        }

        $assignedIds = [];
        $errors = [];
        $orders = Order::whereIn('id', $orderIds)->get()->keyBy('id');

        foreach ($orderIds as $orderId) {
            $order = $orders->get($orderId);
            if (! $order) {
                continue;
            }

            if (! $order->manager_id) {
                $errors[$orderId] = 'Нельзя назначить курьера на заказ без назначенного менеджера';

                continue;
            }

            if (! $user->isOwner() && $order->manager_id && $order->manager_id !== $user->id) {
                $errors[$orderId] = 'Нет прав на назначение курьера';

                continue;
            }

            try {
                $this->orderAssigner->assignCourier($order->id, $courier->id, $user->id, $courier);
                $assignedIds[] = $order->id;
            } catch (\Exception $e) {
                $errors[$orderId] = $e->getMessage();
            }
        }

        return [
            'success' => true,
            'count' => count($assignedIds),
            'assigned_ids' => $assignedIds,
            'errors' => $errors,
        ];
    }

    public function accept(array $orderIds, User $user): array
    {
        $acceptedIds = [];
        $errors = [];

        foreach ($orderIds as $orderId) {
            try {
                $accepted = DB::transaction(function () use ($orderId, $user, &$errors) {
                    $order = Order::where('id', $orderId)->lockForUpdate()->first();
                    if (! $order) {
                        return null;
                    }

                    if ($order->manager_id !== null && $order->manager_id !== $user->id && ! $user->isOwner()) {
                        $errors[$order->id] = 'Заказ уже принят другим менеджером';

                        return null;
                    }

                    if ($order->manager_id === null) {
                        $this->orderAssigner->acceptOrder($order->id, $user->id, $user);
                    }

                    return $order->id;
                });

                if ($accepted) {
                    $acceptedIds[] = $accepted;
                }
            } catch (\Exception $e) {
                $errors[$orderId] = $e->getMessage();
            }
        }

        return [
            'success' => true,
            'count' => count($acceptedIds),
            'accepted_ids' => $acceptedIds,
            'errors' => $errors,
        ];
    }

    public function assignManager(array $orderIds, int $managerId, User $user): array
    {
        $manager = User::find($managerId);
        if (! $manager || (! $manager->isManager() && ! $manager->isOwner())) {
            return ['error' => 'Выбранный пользователь не является менеджером', 'status' => 400];
        }

        $assignedIds = [];
        $errors = [];
        $orders = Order::whereIn('id', $orderIds)->get()->keyBy('id');

        foreach ($orderIds as $orderId) {
            $order = $orders->get($orderId);
            if (! $order) {
                continue;
            }

            try {
                DB::transaction(function () use ($order, $manager, $user) {
                    $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
                    if (! $lockedOrder) {
                        return;
                    }

                    if ($lockedOrder->manager_id === null) {
                        $this->orderAssigner->acceptOrder($lockedOrder->id, $manager->id, $manager);
                    } else {
                        $oldManagerId = $lockedOrder->manager_id;
                        $lockedOrder->skipAuditLog = true;
                        $lockedOrder->update(['manager_id' => $manager->id]);
                        ActionLog::create([
                            'actor_id' => $user->id,
                            'order_id' => $lockedOrder->id,
                            'action_type' => ActionType::ASSIGN,
                            'field_name' => 'manager_id',
                            'old_value' => (string) $oldManagerId,
                            'new_value' => (string) $manager->id,
                            'created_at' => now(),
                        ]);
                        event(new OrderStatusChanged($lockedOrder->fresh()));
                    }
                });
                $assignedIds[] = $orderId;
            } catch (\Exception $e) {
                $errors[$orderId] = $e->getMessage();
            }
        }

        return [
            'success' => true,
            'count' => count($assignedIds),
            'assigned_ids' => $assignedIds,
            'errors' => $errors,
        ];
    }

    public function atWarehouse(array $orderIds, User $user): array
    {
        $updatedIds = [];
        $errors = [];
        $orders = Order::with('delivery')->whereIn('id', $orderIds)->get()->keyBy('id');

        foreach ($orderIds as $orderId) {
            $order = $orders->get($orderId);
            if (! $order) {
                continue;
            }

            if (! Gate::forUser($user)->allows('atWarehouse', $order)) {
                $errors[$orderId] = 'Нет прав на изменение статуса заказа';

                continue;
            }

            try {
                $this->orderStatusMachine->markAtWarehouse($order->id, $user->id);
                $updatedIds[] = $order->id;
            } catch (\Exception $e) {
                $errors[$orderId] = $e->getMessage();
            }
        }

        return [
            'success' => true,
            'count' => count($updatedIds),
            'updated_ids' => $updatedIds,
            'errors' => $errors,
        ];
    }
}
