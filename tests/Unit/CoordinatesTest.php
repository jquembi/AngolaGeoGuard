<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Unit;

use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidCoordinatesException;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

final class CoordinatesTest extends TestCase
{
    public function test_it_rejects_invalid_latitude(): void
    {
        $this->expectException(InvalidCoordinatesException::class);

        new Coordinates(latitude: 120.0, longitude: 13.4925);
    }

    public function test_it_rejects_invalid_longitude(): void
    {
        $this->expectException(InvalidCoordinatesException::class);

        new Coordinates(latitude: -14.9172, longitude: 200.0);
    }

    public function test_it_accepts_lubango_coordinates(): void
    {
        $lubango = new Coordinates(latitude: -14.9172, longitude: 13.4925);

        $this->assertSame(-14.9172, $lubango->latitude);
        $this->assertSame(13.4925, $lubango->longitude);
    }

    public function test_distance_between_luanda_and_lubango_is_approximately_correct(): void
    {
        $luanda = new Coordinates(latitude: -8.8390, longitude: 13.2894);
        $lubango = new Coordinates(latitude: -14.9172, longitude: 13.4925);

        $distanceKm = $luanda->distanceTo($lubango) / 1000;

        // Distancia real aproximada entre Luanda e Lubango: ~680km em linha reta.
        $this->assertGreaterThan(650.0, $distanceKm);
        $this->assertLessThan(710.0, $distanceKm);
    }

    public function test_is_within_radius(): void
    {
        $center = new Coordinates(latitude: -8.8390, longitude: 13.2894);
        $nearby = new Coordinates(latitude: -8.8395, longitude: 13.2900);

        $this->assertTrue($nearby->isWithinRadiusOf($center, 1000));
        $this->assertFalse($nearby->isWithinRadiusOf($center, 1));
    }
}
