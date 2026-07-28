<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

final class LocationResolutionFailed
{
    public function __construct(
        public readonly string $remoteAddr,
        public readonly string $reason,
    ) {
    }
}
