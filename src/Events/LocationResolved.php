<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;

final class LocationResolved
{
    public function __construct(
        public readonly LocationResult $location,
    ) {
    }
}
