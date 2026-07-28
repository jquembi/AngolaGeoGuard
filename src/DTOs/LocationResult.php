<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\DTOs;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * DTO imutavel representando o resultado da resolucao de localizacao
 * de um unico resolvedor/provedor. Ver secao 7.
 */
final class LocationResult
{
    /**
     * @param  array<string>  $evidence  Evidencias tecnicas usadas (ex.: "ip_geoip", "browser_gps")
     */
    public function __construct(
        public readonly ?Coordinates $coordinates,
        public readonly ?string $countryCode,
        public readonly ?string $provinceSlug,
        public readonly ?string $municipalitySlug,
        public readonly ?string $city,
        public readonly string $provider,
        public readonly ConfidenceLevel $confidence,
        public readonly DateTimeImmutable $resolvedAt,
        public readonly ?string $ipAddress = null,
        public readonly ?string $asn = null,
        public readonly ?float $accuracyMeters = null,
        public readonly bool $isVpn = false,
        public readonly bool $isProxy = false,
        public readonly bool $isTor = false,
        public readonly bool $isDatacenter = false,
        public readonly array $evidence = [],
    ) {
    }

    public function isResolved(): bool
    {
        return $this->coordinates !== null || $this->countryCode !== null;
    }

    public function hasSecurityFlags(): bool
    {
        return $this->isVpn || $this->isProxy || $this->isTor || $this->isDatacenter;
    }

    public function toArray(): array
    {
        return [
            'coordinates' => $this->coordinates?->toArray(),
            'country_code' => $this->countryCode,
            'province_slug' => $this->provinceSlug,
            'municipality_slug' => $this->municipalitySlug,
            'city' => $this->city,
            'provider' => $this->provider,
            'confidence' => $this->confidence->value,
            'resolved_at' => $this->resolvedAt->format(DateTimeImmutable::ATOM),
            'ip_address' => $this->ipAddress,
            'asn' => $this->asn,
            'accuracy_meters' => $this->accuracyMeters,
            'is_vpn' => $this->isVpn,
            'is_proxy' => $this->isProxy,
            'is_tor' => $this->isTor,
            'is_datacenter' => $this->isDatacenter,
            'evidence' => $this->evidence,
        ];
    }

    public static function unresolved(string $provider): self
    {
        return new self(
            coordinates: null,
            countryCode: null,
            provinceSlug: null,
            municipalitySlug: null,
            city: null,
            provider: $provider,
            confidence: ConfidenceLevel::VERY_LOW,
            resolvedAt: new DateTimeImmutable(),
        );
    }
}
