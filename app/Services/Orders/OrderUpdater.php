<?php

namespace App\Services\Orders;

use App\Dto\Order\UpdateOrderDto;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\OrderAssigned;
use App\Events\OrderStatusChanged;
use App\Jobs\AggregateFinancialStatsJob;
use App\Jobs\NotifyCourierJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\Concerns\NormalizesOrderItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderUpdater
{
    use NormalizesOrderItems;

    public function __construct(
        protected ActionLogger $actionLogger,
        protected CustomerResolver $customerResolver
    ) {}

    public function updateFromDto(UpdateOrderDto $dto): Order
    {
        return $this->update($dto->orderId, $dto->toArray(), $dto->actorId);
    }

    /**
     * @param  array<string, mixed>|UpdateOrderDto  $data
     */
    public function update(int $orderId, array|UpdateOrderDto $data, int $actorId): Order
    {
        if ($data instanceof UpdateOrderDto) {
            $actorId = $data->actorId;
            $orderId = $data->orderId;
            $data = $data->toArray();
        }

        $actor = User::findOrFail($actorId);
        $statusChanged = false;
        $order = null;

        DB::transaction(function () use ($orderId, $data, $actor, $actorId, &$statusChanged, &$order) {
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $changes = [];

            if (array_key_exists('customer_id', $data) && $order->customer_id != $data['customer_id']) {
                if ($data['customer_id'] !== null) {
                    $validCustomer = Customer::where('id', $data['customer_id'])
                        ->where('site_id', $order->site_id)
                        ->first();
                    if (! $validCustomer) {
                        throw new \InvalidArgumentException('Покупатель не принадлежит сайту заказа');
                    }
                }
                $changes['customer_id'] = [
                    'old' => $order->customer_id,
                    'new' => $data['customer_id'],
                ];
                $order->customer_id = $data['customer_id'];
            }

            if (array_key_exists('external_id', $data)) {
                $newExternalId = $data['external_id'] !== null ? (string) $data['external_id'] : null;
                if ($order->external_id !== $newExternalId) {
                    $changes['external_id'] = [
                        'old' => $order->external_id,
                        'new' => $newExternalId,
                    ];
                    $order->external_id = $newExternalId;
                }
            }

            if (isset($data['client_data']) || isset($data['client_name']) || isset($data['client_phone']) || isset($data['client_email']) || isset($data['client_address'])) {
                $customer = $this->resolveCustomer($order->site_id, $data);
                if ($customer && $order->customer_id != $customer->id) {
                    $changes['customer_id'] = [
                        'old' => $order->customer_id,
                        'new' => $customer->id,
                    ];
                    $order->customer_id = $customer->id;
                }
            }

            if (isset($data['status']) && $order->status->value !== $data['status']) {
                $newStatus = OrderStatus::from($data['status']);

                if (! $actor->isOwner()) {
                    $available = $order->status->getAvailableStatuses();

                    if (! in_array($newStatus, $available, true)) {
                        throw new \InvalidArgumentException('Некорректный переход статуса');
                    }
                }

                $changes['status'] = [
                    'old' => $order->status,
                    'new' => $newStatus,
                ];
                $order->status = $newStatus;
                $statusChanged = true;

                if ($newStatus === OrderStatus::COMPLETED) {
                    $order->payment()
                        ->where('status', PaymentStatus::PENDING)
                        ->whereIn('method', [PaymentMethod::CASH, PaymentMethod::TERMINAL])
                        ->update(['status' => PaymentStatus::PAID]);
                }
            }

            $effectiveSiteId = $data['site_id'] ?? $order->site_id;
            if (isset($data['site_id']) && $data['site_id'] != $order->site_id) {
                $changes['site_id'] = [
                    'old' => $order->site_id,
                    'new' => $data['site_id'],
                ];
                $order->site_id = $data['site_id'];
            }

            $site = $effectiveSiteId ? Site::find($effectiveSiteId) : null;
            $defaultPercent = ($site && $site->default_purchase_percent !== null)
                ? (float) $site->default_purchase_percent
                : (float) config('orders.default_purchase_percent', 15.0);

            $delivery = $order->relationLoaded('delivery')
                ? $order->delivery->first()
                : $order->delivery()->first();

            $items = $this->normalizeOrderItems($data, $defaultPercent, $effectiveSiteId);
            if (! empty($items)) {
                $oldItems = $order->items()->get()->map(fn ($item) => [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'qty' => $item->qty,
                    'price' => (float) $item->price,
                    'purchase_price' => (float) $item->purchase_price,
                ])->toArray();

                $order->items()->delete();
                $order->items()->createMany($items);

                if (json_encode($oldItems) !== json_encode($items)) {
                    $changes['order_items'] = [
                        'old' => $oldItems,
                        'new' => $items,
                    ];
                }

                $prices = $this->calculatePrices($items);
                $deliveryPrice = array_key_exists('delivery_price', $data)
                    ? (is_numeric($data['delivery_price']) ? (float) $data['delivery_price'] : 0.0)
                    : (float) ($delivery?->delivery_price ?? 0.0);
                $newTotalPrice = array_key_exists('total_price', $data) ? (float) $data['total_price'] : round($prices['total_price'] + $deliveryPrice, 2);
                $newPurchasePrice = array_key_exists('purchase_price', $data) ? (float) $data['purchase_price'] : $prices['purchase_price'];

                if ((float) $order->total_price != $newTotalPrice) {
                    $changes['total_price'] = [
                        'old' => $order->total_price,
                        'new' => $newTotalPrice,
                    ];
                    $order->total_price = $newTotalPrice;
                }

                if ((float) $order->purchase_price != $newPurchasePrice) {
                    $changes['purchase_price'] = [
                        'old' => $order->purchase_price,
                        'new' => $newPurchasePrice,
                    ];
                    $order->purchase_price = $newPurchasePrice;
                }
            } else {
                if (array_key_exists('total_price', $data) && is_numeric($data['total_price'])) {
                    $newTotalPrice = (float) $data['total_price'];
                    if ((float) $order->total_price != $newTotalPrice) {
                        $changes['total_price'] = [
                            'old' => $order->total_price,
                            'new' => $newTotalPrice,
                        ];
                        $order->total_price = $newTotalPrice;
                    }
                } elseif (array_key_exists('delivery_price', $data) && is_numeric($data['delivery_price'])) {
                    $oldDelivPrice = (float) ($delivery?->delivery_price ?? 0.0);
                    $newDelivPrice = (float) $data['delivery_price'];
                    $delta = $newDelivPrice - $oldDelivPrice;
                    if ($delta != 0) {
                        $newTotalPrice = round((float) $order->total_price + $delta, 2);
                        $changes['total_price'] = [
                            'old' => $order->total_price,
                            'new' => $newTotalPrice,
                        ];
                        $order->total_price = $newTotalPrice;
                    }
                }

                if (array_key_exists('purchase_price', $data) && is_numeric($data['purchase_price'])) {
                    $newPurchasePrice = (float) $data['purchase_price'];
                    if ((float) $order->purchase_price != $newPurchasePrice) {
                        $changes['purchase_price'] = [
                            'old' => $order->purchase_price,
                            'new' => $newPurchasePrice,
                        ];
                        $order->purchase_price = $newPurchasePrice;
                    }
                }
            }

            if (isset($data['delivery_type']) || array_key_exists('delivery_courier_id', $data) || isset($data['delivery_courier_comment']) || isset($data['delivery_pickup_point']) || isset($data['delivery_address']) || isset($data['delivery_cost']) || array_key_exists('delivery_price', $data) || isset($data['delivery_date']) || isset($data['delivery_slot']) || array_key_exists('delivery_city', $data) || array_key_exists('city', $data)) {
                $delivery = $delivery ?? $order->delivery()->make();
                $oldCourierId = $delivery->user_id;

                if (isset($data['delivery_type']) && ($delivery->type?->value ?? (string) $delivery->type) !== $data['delivery_type']) {
                    $changes['delivery.type'] = ['old' => (string) ($delivery->type?->value ?? $delivery->type), 'new' => (string) $data['delivery_type']];
                    $delivery->type = $data['delivery_type'];
                }
                $targetCity = array_key_exists('delivery_city', $data) ? $data['delivery_city'] : (array_key_exists('city', $data) ? $data['city'] : null);
                if ((array_key_exists('delivery_city', $data) || array_key_exists('city', $data)) && $delivery->city !== $targetCity) {
                    $changes['delivery.city'] = ['old' => (string) $delivery->city, 'new' => (string) $targetCity];
                    $delivery->city = $targetCity;
                }
                if (array_key_exists('delivery_courier_id', $data)) {
                    $newCourierId = ! empty($data['delivery_courier_id']) ? (int) $data['delivery_courier_id'] : null;
                    $delivery->user_id = $newCourierId;
                    $order->courier_id = $newCourierId;
                }
                if (isset($data['delivery_courier_comment']) && $delivery->courier_comment !== $data['delivery_courier_comment']) {
                    $changes['delivery.courier_comment'] = ['old' => (string) $delivery->courier_comment, 'new' => (string) $data['delivery_courier_comment']];
                    $delivery->courier_comment = $data['delivery_courier_comment'];
                }
                if (isset($data['delivery_pickup_point']) && $delivery->pickup_point !== $data['delivery_pickup_point']) {
                    $changes['delivery.pickup_point'] = ['old' => (string) $delivery->pickup_point, 'new' => (string) $data['delivery_pickup_point']];
                    $delivery->pickup_point = $data['delivery_pickup_point'];
                }
                if (isset($data['delivery_address']) && $delivery->delivery_address !== $data['delivery_address']) {
                    $changes['delivery.address'] = ['old' => (string) $delivery->delivery_address, 'new' => (string) $data['delivery_address']];
                    $delivery->delivery_address = $data['delivery_address'];
                }
                if (isset($data['delivery_cost']) && (float) $delivery->delivery_cost != (float) $data['delivery_cost']) {
                    $changes['delivery.cost'] = ['old' => (string) $delivery->delivery_cost, 'new' => (string) $data['delivery_cost']];
                    $delivery->delivery_cost = $data['delivery_cost'];
                }
                if (array_key_exists('delivery_price', $data)) {
                    $newDelivPrice = is_numeric($data['delivery_price']) ? (float) $data['delivery_price'] : 0.0;
                    if ((float) $delivery->delivery_price != $newDelivPrice) {
                        $changes['delivery.price'] = ['old' => (string) $delivery->delivery_price, 'new' => (string) $newDelivPrice];
                        $delivery->delivery_price = $newDelivPrice;
                    }
                }
                if (array_key_exists('delivery_date', $data)) {
                    $newDate = $data['delivery_date'] ?: null;
                    if ($delivery->delivery_date?->toDateString() !== $newDate && (string) $delivery->delivery_date !== $newDate) {
                        $changes['delivery.date'] = ['old' => (string) ($delivery->delivery_date?->toDateString() ?? $delivery->delivery_date), 'new' => (string) $newDate];
                        $delivery->delivery_date = $newDate;
                    }
                }
                if (array_key_exists('delivery_slot', $data)) {
                    $newSlot = $data['delivery_slot'] ?: null;
                    if ($delivery->delivery_slot !== $newSlot) {
                        $changes['delivery.slot'] = ['old' => (string) $delivery->delivery_slot, 'new' => (string) $newSlot];
                        $delivery->delivery_slot = $newSlot;
                    }
                }

                $delivery->save();

                if (isset($data['delivery_courier_id']) && $oldCourierId != $data['delivery_courier_id'] && ! empty($data['delivery_courier_id'])) {
                    $courier = User::find($data['delivery_courier_id']);

                    if (! $courier) {
                        Log::warning('Courier notification skipped: courier not found', [
                            'order_id' => $order->id,
                            'courier_id' => $data['delivery_courier_id'],
                        ]);
                    } elseif (! $courier->isCourier()) {
                        Log::warning('Courier notification skipped: user is not a courier', [
                            'order_id' => $order->id,
                            'courier_id' => $data['delivery_courier_id'],
                        ]);
                    } elseif (! $courier->telegram_user_id) {
                        Log::warning('Courier notification skipped: missing telegram_user_id', [
                            'order_id' => $order->id,
                            'courier_id' => $data['delivery_courier_id'],
                        ]);
                    } else {
                        if (! isset($data['status']) && in_array($order->status, [OrderStatus::CREATED, OrderStatus::ACCEPTED], true)) {
                            $changes['status'] = [
                                'old' => $order->status,
                                'new' => OrderStatus::ASSIGNED,
                            ];
                            $order->status = OrderStatus::ASSIGNED;
                            $statusChanged = true;
                        }

                        $this->actionLogger->logAssign($order, $courier, $actorId);
                        DB::afterCommit(function () use ($order, $courier) {
                            event(new OrderAssigned($order->fresh(), (string) $courier->telegram_user_id));
                            NotifyCourierJob::dispatch($order->fresh(), $courier);
                        });
                    }
                } elseif (array_key_exists('delivery_courier_id', $data) && empty($data['delivery_courier_id']) && $oldCourierId !== null) {
                    $changes['delivery.user_id'] = [
                        'old' => (string) $oldCourierId,
                        'new' => null,
                    ];
                    $delivery->update(['user_id' => null]);
                    $order->courier_id = null;
                    if (! isset($data['status']) && $order->status === OrderStatus::ASSIGNED) {
                        $changes['status'] = [
                            'old' => $order->status,
                            'new' => OrderStatus::ACCEPTED,
                        ];
                        $order->status = OrderStatus::ACCEPTED;
                        $statusChanged = true;
                    }
                }
            }

            if (isset($data['payment_method']) || isset($data['payment_status']) || isset($data['payment_transaction_id']) || isset($data['payment_amount'])) {
                $payment = $order->payment()->first() ?? $order->payment()->make();

                if (isset($data['payment_method']) && ($payment->method?->value ?? (string) $payment->method) !== $data['payment_method']) {
                    $changes['payment.method'] = ['old' => (string) ($payment->method?->value ?? $payment->method), 'new' => (string) $data['payment_method']];
                    $payment->method = $data['payment_method'];
                }
                if (isset($data['payment_status']) && ($payment->status?->value ?? (string) $payment->status) !== $data['payment_status']) {
                    $changes['payment.status'] = ['old' => (string) ($payment->status?->value ?? $payment->status), 'new' => (string) $data['payment_status']];
                    $payment->status = $data['payment_status'];
                }
                if (isset($data['payment_transaction_id']) && $payment->transaction_id !== $data['payment_transaction_id']) {
                    $changes['payment.transaction_id'] = ['old' => (string) $payment->transaction_id, 'new' => (string) $data['payment_transaction_id']];
                    $payment->transaction_id = $data['payment_transaction_id'];
                }
                if (isset($data['payment_amount']) && (float) $payment->amount != (float) $data['payment_amount']) {
                    $changes['payment.amount'] = ['old' => (string) $payment->amount, 'new' => (string) $data['payment_amount']];
                    $payment->amount = $data['payment_amount'];
                }

                $payment->save();
            }

            if (isset($data['discounts'])) {
                $oldDiscounts = $order->discounts()->get()->map(fn ($d) => ['name' => $d->name, 'amount' => $d->amount, 'type' => $d->type])->toArray();
                $order->discounts()->delete();
                $order->discounts()->createMany($data['discounts']);
                if (json_encode($oldDiscounts) !== json_encode($data['discounts'])) {
                    $changes['discounts'] = [
                        'old' => $oldDiscounts,
                        'new' => $data['discounts'],
                    ];
                }
            }

            if (isset($data['extra_data']) && $order->extra_data !== $data['extra_data']) {
                $changes['extra_data'] = [
                    'old' => $order->extra_data,
                    'new' => $data['extra_data'],
                ];
                $order->extra_data = $data['extra_data'];
            }

            if (array_key_exists('is_valuable', $data)) {
                $newIsValuable = filter_var($data['is_valuable'], FILTER_VALIDATE_BOOLEAN);
                if ((bool) $order->is_valuable !== $newIsValuable) {
                    $changes['is_valuable'] = [
                        'old' => (bool) $order->is_valuable,
                        'new' => $newIsValuable,
                    ];
                    $order->is_valuable = $newIsValuable;
                }
            }

            $order->skipAuditLog = true;
            $order->save();

            if ($actor->role === UserRole::MANAGER) {
                $priceFields = array_intersect(['total_price', 'purchase_price'], array_keys($changes));
                if (! empty($priceFields)) {
                    $order->update(['is_edited' => true]);
                }
            }

            foreach ($changes as $field => $values) {
                if ($field === 'status') {
                    $this->actionLogger->logStatusChange($order, $values['old'], $values['new'], $actorId);
                } else {
                    $this->actionLogger->logUpdate($order, $field, $values['old'], $values['new'], $actor);
                }
            }

            if (isset($changes['delivery.cost'])) {
                AggregateFinancialStatsJob::dispatch($order->created_at->toDateString());
            }
        });

        $freshOrder = $order->fresh();
        if ($statusChanged) {
            event(new OrderStatusChanged($freshOrder));
        }

        return $freshOrder;
    }

    private function resolveCustomer(int $siteId, array $data): ?Customer
    {
        return $this->customerResolver->resolve($siteId, $data);
    }
}
