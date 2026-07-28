<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

use JoseQuembi\AngolaGeoGuard\Enums\RiskLevel;

final class GeoRiskDetected
{
    /**
     * @param  array<string>  $signals
     */
    public function __construct(
        public readonly RiskLevel $risk,
        public readonly array $signals,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userId = null,
    ) {
    }
}
