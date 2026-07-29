<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Enums;

/**
 * Nivel de ameaca comportamental calculado ao longo de multiplos
 * pedidos do mesmo sujeito (IP/utilizador), distinto do `RiskLevel`
 * por pedido calculado em GeoAccessPolicyEngine. Enquanto RiskLevel
 * reflete sinais de UM pedido isolado (VPN, Tor, etc), ThreatLevel
 * reflete PADROES ao longo do tempo (viagem impossivel, sondagem de
 * provincias, taxa anormal de pedidos).
 */
enum ThreatLevel: string
{
    case NONE = 'none';
    case WATCHING = 'watching';
    case SUSPICIOUS = 'suspicious';
    case CONFIRMED_THREAT = 'confirmed_threat';

    public function weight(): int
    {
        return match ($this) {
            self::NONE => 0,
            self::WATCHING => 1,
            self::SUSPICIOUS => 2,
            self::CONFIRMED_THREAT => 3,
        };
    }

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 70 => self::CONFIRMED_THREAT,
            $score >= 45 => self::SUSPICIOUS,
            $score >= 20 => self::WATCHING,
            default => self::NONE,
        };
    }
}
