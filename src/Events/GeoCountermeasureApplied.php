<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

use JoseQuembi\AngolaGeoGuard\Enums\CountermeasureAction;

/**
 * Disparado sempre que uma contramedida (challenge, throttle,
 * quarentena) e efetivamente aplicada a um sujeito. A aplicacao host
 * pode ouvir este evento para notificar administradores (email,
 * Slack, SIEM) — ver secao 29 do prompt mestre.
 */
final class GeoCountermeasureApplied
{
    public function __construct(
        public readonly string $subjectKey,
        public readonly CountermeasureAction $action,
        public readonly ?int $quarantineDurationSeconds,
        public readonly int $violationCount,
    ) {
    }
}
