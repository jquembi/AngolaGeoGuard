<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Enums;

/**
 * Nivel de risco calculado a partir de multiplos sinais
 * (VPN, proxy, Tor, incompatibilidade IP/GPS, etc).
 */
enum RiskLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 0,
            self::MEDIUM => 1,
            self::HIGH => 2,
            self::CRITICAL => 3,
        };
    }

    public function exceeds(self $threshold): bool
    {
        return $this->weight() > $threshold->weight();
    }
}
