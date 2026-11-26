<?php

namespace App\Dto\Lead;

class CreateLeadDto
{
    /**
     * @param  array<string, mixed>  $rawPayload
     * @param  array<string, mixed>|null  $extraData
     */
    public function __construct(
        public readonly int $siteId,
        public readonly array $rawPayload,
        public readonly string $type,
        public readonly ?string $name = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $comment = null,
        public readonly ?string $productName = null,
        public readonly ?string $productSku = null,
        public readonly ?string $pageUrl = null,
        public readonly ?string $externalId = null,
        public readonly ?array $extraData = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(int $siteId, array $data): self
    {
        return new self(
            siteId: $siteId,
            rawPayload: $data,
            type: $data['type'] ?? 'callback',
            name: $data['client_name'] ?? ($data['name'] ?? null),
            phone: $data['client_phone'] ?? ($data['phone'] ?? null),
            email: $data['client_email'] ?? ($data['email'] ?? null),
            comment: $data['client_comment'] ?? ($data['comment'] ?? ($data['message'] ?? null)),
            productName: $data['product_name'] ?? null,
            productSku: $data['product_sku'] ?? null,
            pageUrl: $data['page_url'] ?? null,
            externalId: isset($data['external_id']) ? (string) $data['external_id'] : null,
            extraData: isset($data['extra_data']) && is_array($data['extra_data']) ? $data['extra_data'] : null,
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
