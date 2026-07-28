<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Unit;

use JoseQuembi\AngolaGeoGuard\Exceptions\LocationResolutionException;
use JoseQuembi\AngolaGeoGuard\Location\LocationResolutionPipeline;
use JoseQuembi\AngolaGeoGuard\Location\Providers\GeolocationProviderChain;
use JoseQuembi\AngolaGeoGuard\Location\Providers\NullGeolocationProvider;
use JoseQuembi\AngolaGeoGuard\Location\Resolvers\ManualLocationResolver;
use PHPUnit\Framework\TestCase;

final class LocationResolutionPipelineTest extends TestCase
{
    public function test_null_provider_chain_returns_unresolved(): void
    {
        $chain = new GeolocationProviderChain([new NullGeolocationProvider()]);

        $this->assertFalse($chain->locate('8.8.8.8')->isResolved());
    }

    public function test_empty_context_throws_resolution_exception(): void
    {
        $pipeline = (new LocationResolutionPipeline())->withResolver(new ManualLocationResolver());

        $this->expectException(LocationResolutionException::class);

        $pipeline->resolve([]);
    }

    public function test_manual_resolver_resolves_from_context(): void
    {
        $pipeline = (new LocationResolutionPipeline())->withResolver(new ManualLocationResolver());

        $result = $pipeline->resolve(['country_code' => 'AO', 'province_slug' => 'huila']);

        $this->assertTrue($result->isResolved());
        $this->assertSame('huila', $result->provinceSlug);
    }

    public function test_resolve_or_unresolved_never_throws(): void
    {
        $pipeline = (new LocationResolutionPipeline())->withResolver(new ManualLocationResolver());

        $result = $pipeline->resolveOrUnresolved([]);

        $this->assertFalse($result->isResolved());
    }
}
