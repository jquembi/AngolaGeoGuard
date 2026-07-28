<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

final class GeoSecurityIncidentCreated
{
    public function __construct(
        public readonly string $type,
        public readonly string $description,
        public readonly array $context = [],
    ) {
    }
}
