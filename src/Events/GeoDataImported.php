<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

final class GeoDataImported
{
    public function __construct(
        public readonly string $versionLabel,
        public readonly string $entityType,
        public readonly int $recordCount,
    ) {
    }
}
