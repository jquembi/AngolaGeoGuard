<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\DTOs;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Snapshot imutavel dos sinais de UM pedido, usado como entrada do
 * ThreatScorer. Nao guarda dados pessoais alem do estritamente
 * necessario a deteccao (nunca o IP em claro — ver `subjectKey`
 * gerado por hash em BehaviorTrackingService).
 */
final class BehaviorObservation
{
    public function __construct(
        public readonly string $subjectKey,
        public readonly DateTimeImmutable $observedAt,
        public readonly ?Coordinates $coordinates,
        public readonly ?string $countryCode,
        public readonly ?string $provinceSlug,
        public readonly bool $isVpn,
        public readonly bool $isProxy,
        public readonly bool $isTor,
        public readonly bool $allowed,
    ) {
    }
}
