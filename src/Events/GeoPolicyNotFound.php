<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

final class GeoPolicyNotFound
{
    public function __construct(
        public readonly string $identifier,
    ) {
    }
}
