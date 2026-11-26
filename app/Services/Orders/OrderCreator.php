<?php

namespace App\Services\Orders;

use App\Dto\Order\CreateOrderDto;
use App\Enums\ActionType;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderAssigned;
use App\Jobs\NotifyCourierJob;
use App\Models\ActionLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\Concerns\NormalizesOrderItems;
use App\Support\PhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCreator
{
    use NormalizesOrderItems;

    public function __construct(
        protected ActionLogger $actionLogger,
        protected CustomerResolver $customerResolver
    ) {}

    public function createFromDto(CreateOrderDto $dto): Order
    {
        return $this->create($dto->siteId, $dto->toArray(), $dto->actorId);
    }

    /**
     * @param  array<string, mixed>|CreateOrderDto  $data
     */
    public function create(int $siteId, array|CreateOrderDto $data, ?int $actorId = null): Order
    {
        $isValuable = false;
        if ($data instanceof CreateOrderDto) {
            $isValuable = $data->isValuable;
            $actorId = $data->actorId ?? $actorId;
            $siteId = $data->siteId;
            $data = $data->toArray();
        } elseif (isset($data['is_valuable'])) {
            $isValuable = filter_var($data['is_valuable'], FILTER_VALIDATE_BOOLEAN);
        }

        $actor = $actorId ? User::find($actorId) : null;
        $autoAssignManager = $actor && $actor->isManager();

        $site = Site::find($siteId);
        $defaultPercent = ($site && $site->default_purchase_percent !== null)
            ? (float) $site->default_purchase_percent
            : (float) config('orders.default_purchase_percent', 15.0);

        $orderItems = $this->normalizeOrderItems($data, $defaultPercent, $siteId);
        $prices = $this->calculatePrices($orderItems);

        $deliveryPrice = isset($data['delivery_price']) && is_numeric($data['delivery_price'])
            ? (float) $data['delivery_price']
            : 0.0;
        $prices['total_price'] += $deliveryPrice;

        if (isset($data['total_price']) && is_numeric($data['total_price'])) {
            $rawTotal = (float) $data['total_price'];
            if ($rawTotal >= 0) {
                $prices['total_price'] = $rawTotal;
            }
        }
        if (isset($data['purchase_price']) && is_numeric($data['purchase_price'])) {
            $rawPurchase = (float) $data['purchase_price'];
            if ($rawPurchase >= 0 && ($actor !== null || $prices['purchase_price'] == 0)) {
                $prices['purchase_price'] = $rawPurchase;
            }
        }

        try {
            $order = DB::transaction(function () use ($siteId, $data, $actorId, $autoAssignManager, $actor, $prices, $orderItems, $isValuable, $deliveryPrice) {
                $customer = $this->resolveCustomer($siteId, $data);

                $externalId = $data['external_id'] ?? ($data['external_order_id'] ?? ($data['extra_data']['external_order_id'] ?? ($data['extra_data']['external_id'] ?? null)));

                $courierId = ! empty($data['delivery_courier_id']) ? (int) $data['delivery_courier_id'] : null;

                $order = new Order([
                    'site_id' => $siteId,
                    'external_id' => $externalId !== null ? (string) $externalId : null,
                    'manager_id' => $autoAssignManager ? $actor->id : null,
                    'courier_id' => $courierId,
                    'customer_id' => $customer?->id,
                    'total_price' => round($prices['total_price'], 2),
                    'purchase_price' => round($prices['purchase_price'], 2),
                    'status' => OrderStatus::CREATED,
                    'is_valuable' => $isValuable,
                    'extra_data' => $data['extra_data'] ?? null,
                ]);

                $order->skipAuditLog = true;
                $order->save();

                if (! empty($orderItems)) {
                    $order->items()->createMany($orderItems);
                }

                $deliveryType = $data['delivery_type'] ?? DeliveryType::COURIER->value;
                $order->delivery()->create([
                    'type' => $deliveryType,
                    'city' => $data['delivery_city'] ?? ($data['city'] ?? null),
                    'user_id' => $data['delivery_courier_id'] ?? null,
                    'courier_comment' => $data['delivery_courier_comment'] ?? null,
                    'pickup_point' => $data['delivery_pickup_point'] ?? null,
                    'delivery_address' => $data['delivery_address'] ?? ($customer?->address ?? null),
                    'delivery_cost' => isset($data['delivery_cost']) && is_numeric($data['delivery_cost']) ? (float) $data['delivery_cost'] : 0,
                    'delivery_price' => $deliveryPrice,
                    'delivery_date' => $data['delivery_date'] ?? null,
                    'delivery_slot' => $data['delivery_slot'] ?? null,
                ]);

                $paymentMethod = $data['payment_method'] ?? PaymentMethod::CASH->value;
                $paymentStatus = $data['payment_status'] ?? PaymentStatus::PENDING->value;
                $order->payment()->create([
                    'method' => $paymentMethod,
                    'status' => $paymentStatus,
                    'transaction_id' => $data['payment_transaction_id'] ?? null,
                    'amount' => $data['payment_amount'] ?? $order->total_price,
                ]);

                if (! empty($data['discounts'] ?? [])) {
                    $order->discounts()->createMany($data['discounts']);
                }

                $clientComment = $data['client_comment'] ?? ($data['comment'] ?? null);
                if (! empty($clientComment) && is_string($clientComment) && trim($clientComment) !== '') {
                    $order->comments()->create([
                        'user_id' => null,
                        'author_type' => 'client',
                        'body' => trim($clientComment),
                    ]);
                }

                $this->actionLogger->logCreate($order, $actorId);

                if ($autoAssignManager) {
                    ActionLog::create([
                        'actor_id' => $actorId,
                        'order_id' => $order->id,
                        'action_type' => ActionType::ASSIGN,
                        'field_name' => 'manager_id',
                        'old_value' => '',
                        'new_value' => (string) $actor->id,
                        'created_at' => now(),
                    ]);
                }

                return $order;
            });
        } catch (QueryException $e) {
            $externalId = $data['external_id'] ?? ($data['external_order_id'] ?? ($data['extra_data']['external_order_id'] ?? ($data['extra_data']['external_id'] ?? null)));
            if ($externalId) {
                $existing = Order::where('site_id', $siteId)
                    ->where('external_id', (string) $externalId)
                    ->where('status', '!=', OrderStatus::COMPLETED)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            throw $e;
        }

        if (! empty($data['delivery_courier_id'])) {
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
                $freshOrder = $order->fresh();
                event(new OrderAssigned($freshOrder, (string) $courier->telegram_user_id));
                NotifyCourierJob::dispatch($freshOrder, $courier);
            }
        }

        return $order;
    }

    private function resolveCustomer(int $siteId, array $data): ?Customer
    {
        return $this->customerResolver->resolve($siteId, $data);
    }

    public function calculateTotalPrice(array $data): float
    {
        if (isset($data['total_price']) && is_numeric($data['total_price'])) {
            return round((float) $data['total_price'], 2);
        }

        $orderItems = $this->normalizeOrderItems($data, null, $data['site_id'] ?? null);
        $prices = $this->calculatePrices($orderItems);
        $deliveryPrice = isset($data['delivery_price']) && is_numeric($data['delivery_price'])
            ? (float) $data['delivery_price']
            : 0.0;

        return round($prices['total_price'] + $deliveryPrice, 2);
    }

    public function findRecentDuplicate(
        int $siteId,
        ?string $phone,
        ?float $totalPrice = null,
        ?int $windowMinutes = null,
        ?string $externalId = null
    ): ?Order {
        if ($externalId) {
            $existing = Order::where('site_id', $siteId)
                ->where('external_id', (string) $externalId)
                ->where('status', '!=', OrderStatus::COMPLETED)
                ->latest('id')
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $windowMinutes = $windowMinutes ?? (int) config('orders.dedup_minutes', 20);
        if ($windowMinutes <= 0 || ! $phone) {
            return null;
        }

        $normalized = PhoneNumber::normalize($phone);
        if (! $normalized) {
            return null;
        }

        $query = Order::where('site_id', $siteId)
            ->where('status', OrderStatus::CREATED)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->whereHas('customer', function ($query) use ($normalized) {
                $query->where('phone', $normalized)
                    ->orWhere('phone', '+'.$normalized);
            });

        if ($totalPrice !== null) {
            $query->where('total_price', round($totalPrice, 2));
        }

        return $query->latest('id')->first();
    }

    /**
     * @param  array<string, mixed>|CreateOrderDto  $data
     */
    public function isDuplicateAndAcquireLock(Site $site, array|CreateOrderDto $data): bool
    {
        $payload = $data instanceof CreateOrderDto ? $data->rawPayload : $data;
        $phone = $payload['client_phone'] ?? ($payload['client_data']['phone'] ?? null);
        $normalizedPhone = PhoneNumber::normalize($phone);

        $externalId = $payload['external_id'] ?? null;
        $dedupMinutes = (int) config('orders.dedup_minutes', 20);

        if ($externalId) {
            $extPendingKey = "incoming_pending_ext:{$site->id}:{$externalId}";
            $ttl = now()->addMinutes($dedupMinutes > 0 ? $dedupMinutes : 20);
            $acquired = Cache::add($extPendingKey, true, $ttl);

            if (! $acquired) {
                $isCompletedInDb = Order::where('site_id', $site->id)
                    ->where('external_id', (string) $externalId)
                    ->where('status', OrderStatus::COMPLETED)
                    ->exists();

                if ($isCompletedInDb) {
                    Cache::put($extPendingKey, true, $ttl);
                    $isDuplicate = false;
                } else {
                    $isDuplicate = true;
                }
            } else {
                $isDuplicate = (bool) $this->findRecentDuplicate($site->id, null, null, null, (string) $externalId);
            }

            if ($isDuplicate) {
                Log::channel('webhooks')->info('Incoming order deduplicated by external_id', [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'external_id' => $externalId,
                ]);

                return true;
            }
        }

        if ($dedupMinutes > 0 && $normalizedPhone) {
            $totalPrice = $this->calculateTotalPrice($payload);
            $formattedPrice = number_format($totalPrice, 2, '.', '');
            $pendingKey = "incoming_pending:{$site->id}:{$normalizedPhone}:{$formattedPrice}";
            $ttl = now()->addMinutes($dedupMinutes);

            $acquired = Cache::add($pendingKey, true, $ttl);

            if (! $acquired) {
                $isCompletedInDb = Order::where('site_id', $site->id)
                    ->where('created_at', '>=', now()->subMinutes($dedupMinutes))
                    ->where('status', OrderStatus::COMPLETED)
                    ->where('total_price', round($totalPrice, 2))
                    ->whereHas('customer', function ($query) use ($normalizedPhone) {
                        $query->where('phone', $normalizedPhone)
                            ->orWhere('phone', '+'.$normalizedPhone);
                    })
                    ->exists();

                if ($isCompletedInDb) {
                    Cache::put($pendingKey, true, $ttl);
                    $isDuplicate = false;
                } else {
                    $isDuplicate = true;
                }
            } else {
                $isDuplicate = (bool) $this->findRecentDuplicate($site->id, $normalizedPhone, $totalPrice, $dedupMinutes);
            }

            if ($isDuplicate) {
                Log::channel('webhooks')->info('Incoming order deduplicated', [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'phone' => $normalizedPhone,
                    'total_price' => $totalPrice,
                ]);

                return true;
            }
        }

        return false;
    }
}
