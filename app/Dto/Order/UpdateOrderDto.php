<?php

namespace App\Dto\Order;

class UpdateOrderDto
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public readonly int $orderId,
        public readonly array $rawPayload,
        public readonly int $actorId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(int $orderId, array $data, int $actorId): self
    {
        return new self($orderId, $data, $actorId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawPayload;
    }
}
