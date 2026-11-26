<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderStatusMachine
{
    public function __construct(
        protected ActionLogger $actionLogger
    ) {}

    public function changeStatus(int $orderId, OrderStatus $status, int $actorId): Order
    {
        $actor = User::find($actorId);

        $order = DB::transaction(function () use ($orderId, $status, $actorId, $actor) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $oldStatus = $order->status;

            if (! $actor || ! $actor->isOwner()) {
                $available = $order->status->getAvailableStatuses();
                if (! in_array($status, $available, true)) {
                    throw new \InvalidArgumentException('Некорректный переход статуса');
                }
            }

            if ($status === OrderStatus::COMPLETED && $order->requiresClientSignature()) {
                $existingDelivery = $order->delivery()->first();
                if (! $existingDelivery || empty($existingDelivery->signature_path)) {
                    throw new \InvalidArgumentException('Для ценного отправления требуется подпись клиента');
                }
            }

            $order->skipAuditLog = true;
            $order->update(['status' => $status]);

            if ($status === OrderStatus::COMPLETED) {
                $this->closePendingDeliveryPayment($order);
                $this->clearDedupCache($order);
            } elseif ($status === OrderStatus::CANCELLED) {
                $this->clearDedupCache($order);
            }

            $this->actionLogger->logStatusChange($order, $oldStatus, $status, $actorId);

            return $order;
        });

        $freshOrder = $order->fresh();
        event(new OrderStatusChanged($freshOrder));

        return $freshOrder;
    }

    public function markAtWarehouse(int $orderId, int $actorId): Order
    {
        $actor = User::find($actorId);

        $order = DB::transaction(function () use ($orderId, $actorId, $actor) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $oldStatus = $order->status;
            $newStatus = OrderStatus::AT_WAREHOUSE;

            if (! $actor || ! $actor->isOwner()) {
                $available = $order->status->getAvailableStatuses();
                if (! in_array($newStatus, $available, true)) {
                    throw new \InvalidArgumentException('Некорректный переход статуса');
                }
            }

            $order->skipAuditLog = true;
            $order->update(['status' => $newStatus]);

            $this->actionLogger->logStatusChange($order, $oldStatus, $newStatus, $actorId);

            return $order;
        });

        $freshOrder = $order->fresh();
        event(new OrderStatusChanged($freshOrder));

        return $freshOrder;
    }

    public function markComplete(int $orderId, int $actorId): Order
    {
        $actor = User::find($actorId);

        $order = DB::transaction(function () use ($orderId, $actorId, $actor) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $oldStatus = $order->status;
            $newStatus = OrderStatus::COMPLETED;

            if (! $actor || ! $actor->isOwner()) {
                $available = $order->status->getAvailableStatuses();
                if (! in_array($newStatus, $available, true)) {
                    throw new \InvalidArgumentException('Некорректный переход статуса');
                }
            }

            if ($order->requiresClientSignature()) {
                $existingDelivery = $order->delivery()->first();
                if (! $existingDelivery || empty($existingDelivery->signature_path)) {
                    throw new \InvalidArgumentException('Для ценного отправления требуется подпись клиента');
                }
            }

            $order->skipAuditLog = true;
            $order->update(['status' => $newStatus]);

            $this->closePendingDeliveryPayment($order);
            $this->clearDedupCache($order);

            $this->actionLogger->logStatusChange($order, $oldStatus, $newStatus, $actorId);

            return $order;
        });

        $freshOrder = $order->fresh();
        event(new OrderStatusChanged($freshOrder));

        return $freshOrder;
    }

    protected function clearDedupCache(Order $order): void
    {
        if ($order->external_id) {
            Cache::forget("incoming_pending_ext:{$order->site_id}:{$order->external_id}");
        }

        $order->loadMissing('customer');
        $phone = $order->customer?->phone;
        if ($phone) {
            $normalizedPhone = PhoneNumber::normalize($phone);
            if ($normalizedPhone) {
                $formattedPrice = number_format((float) $order->total_price, 2, '.', '');
                Cache::forget("incoming_pending:{$order->site_id}:{$normalizedPhone}:{$formattedPrice}");
            }
        }
    }

    protected function closePendingDeliveryPayment(Order $order): void
    {
        $order->payment()
            ->where('status', PaymentStatus::PENDING)
            ->whereIn('method', [PaymentMethod::CASH, PaymentMethod::TERMINAL])
            ->update(['status' => PaymentStatus::PAID]);
    }

    public function markCancelled(int $orderId, int $actorId): Order
    {
        $actor = User::find($actorId);

        $order = DB::transaction(function () use ($orderId, $actorId, $actor) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $oldStatus = $order->status;
            $newStatus = OrderStatus::CANCELLED;

            if (! $actor || ! $actor->isOwner()) {
                if ($actor?->isManager() && $order->manager_id !== $actor->id) {
                    throw new \InvalidArgumentException('Менеджер может отменять только свои заказы');
                }
                $available = $order->status->getAvailableStatuses();
                if (! in_array($newStatus, $available, true)) {
                    throw new \InvalidArgumentException('Некорректный переход статуса');
                }
            }

            $order->skipAuditLog = true;
            $order->update(['status' => $newStatus]);

            $this->clearDedupCache($order);

            $this->actionLogger->logStatusChange($order, $oldStatus, $newStatus, $actorId);

            return $order;
        });

        $freshOrder = $order->fresh();
        event(new OrderStatusChanged($freshOrder));

        return $freshOrder;
    }
}
