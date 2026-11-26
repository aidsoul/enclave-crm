<?php

namespace App\Dto\Order;

class CreateOrderDto
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $discounts
     * @param  array<string, mixed>|null  $extraData
     * @param  array<string, mixed>|null  $clientData
     */
    public function __construct(
        public readonly int $siteId,
        public readonly array $rawPayload,
        public readonly ?int $actorId = null,
        public readonly ?string $externalId = null,
        public readonly ?float $totalPrice = null,
        public readonly ?float $purchasePrice = null,
        public readonly ?string $clientName = null,
        public readonly ?string $clientPhone = null,
        public readonly ?string $clientEmail = null,
        public readonly ?string $clientAddress = null,
        public readonly ?int $customerId = null,
        public readonly ?string $deliveryType = null,
        public readonly ?string $deliveryCity = null,
        public readonly ?int $deliveryCourierId = null,
        public readonly ?string $deliveryCourierComment = null,
        public readonly ?string $deliveryPickupPoint = null,
        public readonly ?string $deliveryAddress = null,
        public readonly ?float $deliveryCost = null,
        public readonly ?float $deliveryPrice = null,
        public readonly ?string $deliveryDate = null,
        public readonly ?string $deliverySlot = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $paymentStatus = null,
        public readonly ?string $paymentTransactionId = null,
        public readonly ?float $paymentAmount = null,
        public readonly ?string $clientComment = null,
        public readonly bool $isValuable = false,
        public readonly array $items = [],
        public readonly array $discounts = [],
        public readonly ?array $extraData = null,
        public readonly ?array $clientData = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(int $siteId, array $data, ?int $actorId = null): self
    {
        return new self(
            siteId: $siteId,
            rawPayload: $data,
            actorId: $actorId,
            externalId: isset($data['external_id']) ? (string) $data['external_id'] : ($data['external_order_id'] ?? null),
            totalPrice: isset($data['total_price']) && is_numeric($data['total_price']) ? (float) $data['total_price'] : null,
            purchasePrice: isset($data['purchase_price']) && is_numeric($data['purchase_price']) ? (float) $data['purchase_price'] : null,
            clientName: $data['client_name'] ?? null,
            clientPhone: $data['client_phone'] ?? null,
            clientEmail: $data['client_email'] ?? null,
            clientAddress: $data['client_address'] ?? null,
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            deliveryType: $data['delivery_type'] ?? null,
            deliveryCity: $data['delivery_city'] ?? ($data['city'] ?? null),
            deliveryCourierId: isset($data['delivery_courier_id']) && $data['delivery_courier_id'] !== '' ? (int) $data['delivery_courier_id'] : null,
            deliveryCourierComment: $data['delivery_courier_comment'] ?? null,
            deliveryPickupPoint: $data['delivery_pickup_point'] ?? null,
            deliveryAddress: $data['delivery_address'] ?? null,
            deliveryCost: isset($data['delivery_cost']) && is_numeric($data['delivery_cost']) ? (float) $data['delivery_cost'] : null,
            deliveryPrice: isset($data['delivery_price']) && is_numeric($data['delivery_price']) ? (float) $data['delivery_price'] : null,
            deliveryDate: $data['delivery_date'] ?? null,
            deliverySlot: $data['delivery_slot'] ?? null,
            paymentMethod: $data['payment_method'] ?? null,
            paymentStatus: $data['payment_status'] ?? null,
            paymentTransactionId: $data['payment_transaction_id'] ?? null,
            paymentAmount: isset($data['payment_amount']) && is_numeric($data['payment_amount']) ? (float) $data['payment_amount'] : null,
            clientComment: $data['client_comment'] ?? ($data['comment'] ?? null),
            isValuable: isset($data['is_valuable']) ? filter_var($data['is_valuable'], FILTER_VALIDATE_BOOLEAN) : false,
            items: (array) ($data['order_items'] ?? ($data['items'] ?? [])),
            discounts: (array) ($data['discounts'] ?? []),
            extraData: isset($data['extra_data']) && is_array($data['extra_data']) ? $data['extra_data'] : null,
            clientData: isset($data['client_data']) && is_array($data['client_data']) ? $data['client_data'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawPayload;
    }
}
