<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

use JoseQuembi\AngolaGeoGuard\Models\GeoAccessExceptionGrant;

final class GeoExceptionRevoked
{
    public function __construct(
        public readonly GeoAccessExceptionGrant $exception,
        public readonly string $revokedBy,
    ) {
    }
}
