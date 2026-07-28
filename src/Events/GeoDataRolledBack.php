<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

final class GeoDataRolledBack
{
    public function __construct(
        public readonly string $fromVersionLabel,
        public readonly string $toVersionLabel,
        public readonly string $rolledBackBy,
    ) {
    }
}
