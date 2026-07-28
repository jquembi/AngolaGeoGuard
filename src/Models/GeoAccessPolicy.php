<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;
use Ramsey\Uuid\Uuid;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $mode
 * @property array|null $allowed_countries
 * @property array|null $allowed_provinces
 * @property array|null $blocked_provinces
 * @property array|null $allowed_geofences
 * @property string $minimum_confidence
 * @property bool $block_vpn
 * @property bool $block_proxy
 * @property bool $block_tor
 * @property bool $block_datacenter_ip
 * @property bool $require_verified_device
 * @property string|null $tenant_id
 * @property bool $is_active
 */
final class GeoAccessPolicy extends Model
{
    protected $table = 'geo_access_policies';

    protected $fillable = [
        'uuid', 'name', 'slug', 'mode', 'allowed_countries', 'allowed_provinces',
        'blocked_provinces', 'allowed_geofences', 'minimum_confidence',
        'block_vpn', 'block_proxy', 'block_tor', 'block_datacenter_ip',
        'require_verified_device', 'tenant_id', 'is_active', 'metadata',
    ];

    protected $casts = [
        'allowed_countries' => 'array',
        'allowed_provinces' => 'array',
        'blocked_provinces' => 'array',
        'allowed_geofences' => 'array',
        'block_vpn' => 'boolean',
        'block_proxy' => 'boolean',
        'block_tor' => 'boolean',
        'block_datacenter_ip' => 'boolean',
        'require_verified_device' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $policy): void {
            $policy->uuid ??= Uuid::uuid4()->toString();
        });
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(GeoPolicyAssignment::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(GeoAccessExceptionGrant::class);
    }

    /**
     * Converte o modelo Eloquent na DTO framework-agnostic usada pelo
     * GeoAccessPolicyEngine.
     */
    public function toConfig(): GeoAccessPolicyConfig
    {
        return new GeoAccessPolicyConfig(
            identifier: $this->slug,
            mode: AccessMode::from($this->mode),
            allowedCountries: $this->allowed_countries ?? ['AO'],
            allowedProvinces: $this->allowed_provinces ?? [],
            blockedProvinces: $this->blocked_provinces ?? [],
            allowedGeofenceSlugs: $this->allowed_geofences ?? [],
            minimumConfidence: ConfidenceLevel::fromString($this->minimum_confidence),
            blockVpn: $this->block_vpn,
            blockProxy: $this->block_proxy,
            blockTor: $this->block_tor,
            blockDatacenter: $this->block_datacenter_ip,
            isActive: $this->is_active,
        );
    }
}
