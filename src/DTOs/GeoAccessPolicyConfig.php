<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\DTOs;

use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;

/**
 * Configuracao imutavel de uma politica de acesso geografico,
 * desacoplada do Eloquent para que o motor de decisao
 * (GeoAccessPolicyEngine) possa ser testado e usado sem Laravel.
 * O model GeoAccessPolicy expoe `toConfig()` para produzir esta DTO.
 */
final class GeoAccessPolicyConfig
{
    /**
     * @param  array<string>  $allowedCountries  Codigos ISO (ex.: ['AO'])
     * @param  array<string>  $allowedProvinces  Slugs de provincia
     * @param  array<string>  $blockedProvinces  Slugs de provincia
     * @param  array<string>  $allowedGeofenceSlugs
     */
    public function __construct(
        public readonly ?string $identifier,
        public readonly AccessMode $mode,
        public readonly array $allowedCountries = ['AO'],
        public readonly array $allowedProvinces = [],
        public readonly array $blockedProvinces = [],
        public readonly array $allowedGeofenceSlugs = [],
        public readonly ConfidenceLevel $minimumConfidence = ConfidenceLevel::MEDIUM,
        public readonly bool $blockVpn = false,
        public readonly bool $blockProxy = false,
        public readonly bool $blockTor = true,
        public readonly bool $blockDatacenter = false,
        public readonly bool $isActive = true,
    ) {
    }

    public static function global(?string $identifier = null): self
    {
        return new self(identifier: $identifier, mode: AccessMode::GLOBAL);
    }

    public static function angolaOnly(?string $identifier = null): self
    {
        return new self(identifier: $identifier, mode: AccessMode::ANGOLA_ONLY);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            identifier: $data['identifier'] ?? $data['name'] ?? null,
            mode: $data['mode'] instanceof AccessMode ? $data['mode'] : AccessMode::from($data['mode']),
            allowedCountries: $data['allowed_countries'] ?? ['AO'],
            allowedProvinces: $data['allowed_provinces'] ?? [],
            blockedProvinces: $data['blocked_provinces'] ?? [],
            allowedGeofenceSlugs: $data['allowed_geofences'] ?? [],
            minimumConfidence: isset($data['minimum_confidence'])
                ? ($data['minimum_confidence'] instanceof ConfidenceLevel
                    ? $data['minimum_confidence']
                    : ConfidenceLevel::fromString($data['minimum_confidence']))
                : ConfidenceLevel::MEDIUM,
            blockVpn: $data['block_vpn'] ?? false,
            blockProxy: $data['block_proxy'] ?? false,
            blockTor: $data['block_tor'] ?? true,
            blockDatacenter: $data['block_datacenter_ip'] ?? false,
            isActive: $data['is_active'] ?? true,
        );
    }
}
