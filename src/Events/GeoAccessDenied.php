<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessDecision;

final class GeoAccessDenied
{
    public function __construct(
        public readonly GeoAccessDecision $decision,
    ) {
    }
}
