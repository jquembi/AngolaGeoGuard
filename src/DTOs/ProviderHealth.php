<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\DTOs;

use DateTimeImmutable;

final class ProviderHealth
{
    public function __construct(
        public readonly string $providerName,
        public readonly bool $healthy,
        public readonly ?float $latencyMs,
        public readonly DateTimeImmutable $checkedAt,
        public readonly ?string $message = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'provider_name' => $this->providerName,
            'healthy' => $this->healthy,
            'latency_ms' => $this->latencyMs,
            'checked_at' => $this->checkedAt->format(DateTimeImmutable::ATOM),
            'message' => $this->message,
        ];
    }
}
