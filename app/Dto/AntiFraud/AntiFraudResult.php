<?php

namespace App\Dto\AntiFraud;

class AntiFraudResult
{
    /**
     * @param  list<string>  $flags
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly int $score,
        public readonly array $flags,
        public readonly array $reasons,
        public readonly string $fingerprint,
        public readonly ?string $clientIp = null,
        public readonly ?string $physicalIp = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $acceptLanguage = null
    ) {}

    public function isBlocked(): bool
    {
        return $this->score >= 75;
    }

    public function isSuspicious(): bool
    {
        return $this->score >= 50;
    }

    /**
     * @return array<string, mixed>
     */
    public function toTelemetryArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'client_ip' => $this->clientIp,
            'physical_ip' => $this->physicalIp,
            'user_agent' => $this->userAgent ? mb_substr($this->userAgent, 0, 255) : null,
            'fraud_score' => $this->score,
            'flags' => $this->flags,
            'reasons' => $this->reasons,
            'is_suspicious' => $this->isSuspicious(),
            'is_blocked' => $this->isBlocked(),
        ];
    }
}
