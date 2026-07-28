<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Unit;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;
use JoseQuembi\AngolaGeoGuard\Enums\DecisionReasonCode;
use JoseQuembi\AngolaGeoGuard\Services\GeoAccessPolicyEngine;
use JoseQuembi\AngolaGeoGuard\Spatial\InMemorySpatialEngine;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

final class GeoAccessPolicyEngineTest extends TestCase
{
    private GeoAccessPolicyEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new GeoAccessPolicyEngine(new InMemorySpatialEngine());
    }

    private function location(array $overrides = []): LocationResult
    {
        return new LocationResult(
            coordinates: $overrides['coordinates'] ?? new Coordinates(-14.9172, 13.4925),
            countryCode: $overrides['countryCode'] ?? 'AO',
            provinceSlug: $overrides['provinceSlug'] ?? 'huila',
            municipalitySlug: null,
            city: null,
            provider: 'test',
            confidence: $overrides['confidence'] ?? ConfidenceLevel::HIGH,
            resolvedAt: new DateTimeImmutable(),
            isVpn: $overrides['isVpn'] ?? false,
            isProxy: $overrides['isProxy'] ?? false,
            isTor: $overrides['isTor'] ?? false,
            isDatacenter: $overrides['isDatacenter'] ?? false,
        );
    }

    public function test_angola_only_allows_ao_and_denies_others(): void
    {
        $policy = GeoAccessPolicyConfig::angolaOnly('test-angola');

        $this->assertTrue($this->engine->evaluate($this->location(), $policy)->allowed);

        $denied = $this->engine->evaluate($this->location(['countryCode' => 'PT']), $policy);
        $this->assertTrue($denied->denied());
        $this->assertSame(DecisionReasonCode::ACCESS_DENIED_COUNTRY, $denied->reasonCode);
    }

    public function test_province_only_mode(): void
    {
        $policy = GeoAccessPolicyConfig::fromArray(['mode' => AccessMode::PROVINCE_ONLY, 'allowed_provinces' => ['huila']]);

        $this->assertTrue($this->engine->evaluate($this->location(['provinceSlug' => 'huila']), $policy)->allowed);
        $this->assertTrue($this->engine->evaluate($this->location(['provinceSlug' => 'luanda']), $policy)->denied());
    }

    public function test_multiple_provinces_mode(): void
    {
        $policy = GeoAccessPolicyConfig::fromArray([
            'mode' => AccessMode::MULTIPLE_PROVINCES,
            'allowed_provinces' => ['huila', 'benguela', 'namibe'],
        ]);

        $this->assertTrue($this->engine->evaluate($this->location(['provinceSlug' => 'benguela']), $policy)->allowed);
        $this->assertTrue($this->engine->evaluate($this->location(['provinceSlug' => 'luanda']), $policy)->denied());
    }

    public function test_blocklist_mode(): void
    {
        $policy = GeoAccessPolicyConfig::fromArray(['mode' => AccessMode::BLOCKLIST, 'blocked_provinces' => ['cabinda']]);

        $this->assertTrue($this->engine->evaluate($this->location(['provinceSlug' => 'cabinda']), $policy)->denied());
        $this->assertTrue($this->engine->evaluate($this->location(['provinceSlug' => 'huila']), $policy)->allowed);
    }

    public function test_global_mode_still_enforces_vpn_block(): void
    {
        $policy = GeoAccessPolicyConfig::fromArray(['mode' => AccessMode::GLOBAL, 'block_vpn' => true]);

        $decision = $this->engine->evaluate($this->location(['isVpn' => true]), $policy);

        $this->assertTrue($decision->denied());
        $this->assertSame(DecisionReasonCode::ACCESS_DENIED_VPN, $decision->reasonCode);
    }

    public function test_tor_is_blocked_by_default(): void
    {
        $policy = GeoAccessPolicyConfig::angolaOnly();

        $decision = $this->engine->evaluate($this->location(['isTor' => true]), $policy);

        $this->assertTrue($decision->denied());
        $this->assertSame(DecisionReasonCode::ACCESS_DENIED_TOR, $decision->reasonCode);
    }

    public function test_low_confidence_is_denied(): void
    {
        $policy = GeoAccessPolicyConfig::angolaOnly();

        $decision = $this->engine->evaluate($this->location(['confidence' => ConfidenceLevel::VERY_LOW]), $policy);

        $this->assertTrue($decision->denied());
        $this->assertSame(DecisionReasonCode::ACCESS_DENIED_LOW_CONFIDENCE, $decision->reasonCode);
    }

    public function test_custom_geofence_mode(): void
    {
        $policy = GeoAccessPolicyConfig::fromArray(['mode' => AccessMode::CUSTOM_GEOFENCE, 'allowed_geofences' => ['test-zone']]);

        $geometries = [
            'test-zone' => [
                'type' => 'Polygon',
                'coordinates' => [[[13.0, -15.0], [14.0, -15.0], [14.0, -14.0], [13.0, -14.0], [13.0, -15.0]]],
            ],
        ];

        $inside = $this->engine->evaluate(
            $this->location(['coordinates' => new Coordinates(-14.9172, 13.4925)]),
            $policy,
            $geometries,
        );
        $this->assertTrue($inside->allowed);

        $outside = $this->engine->evaluate(
            $this->location(['coordinates' => new Coordinates(-8.8390, 13.2894)]),
            $policy,
            $geometries,
        );
        $this->assertTrue($outside->denied());
        $this->assertSame(DecisionReasonCode::ACCESS_DENIED_OUTSIDE_GEOFENCE, $outside->reasonCode);
    }

    public function test_hybrid_mode_blocklist_takes_priority_over_allowlist(): void
    {
        $policy = GeoAccessPolicyConfig::fromArray([
            'mode' => AccessMode::HYBRID,
            'allowed_provinces' => ['huila'],
            'blocked_provinces' => ['huila'],
        ]);

        $decision = $this->engine->evaluate($this->location(['provinceSlug' => 'huila']), $policy);

        $this->assertTrue($decision->denied());
    }

    public function test_risk_level_escalates_with_multiple_flags(): void
    {
        $policy = GeoAccessPolicyConfig::angolaOnly();

        $decision = $this->engine->evaluate($this->location(['isVpn' => true, 'isProxy' => true, 'isTor' => false, 'confidence' => ConfidenceLevel::HIGH]), $policy);

        $this->assertSame('critical', $decision->risk->value);
    }
}
