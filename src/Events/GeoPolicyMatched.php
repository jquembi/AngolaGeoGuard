<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

final class GeoPolicyMatched
{
    public function __construct(
        public readonly string $policyIdentifier,
        public readonly string $assignableType,
        public readonly string $assignableId,
    ) {
    }
}
