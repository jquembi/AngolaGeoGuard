<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Unit;

use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidGeometryException;
use JoseQuembi\AngolaGeoGuard\Spatial\InMemorySpatialEngine;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

final class InMemorySpatialEngineTest extends TestCase
{
    private InMemorySpatialEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new InMemorySpatialEngine();
    }

    /**
     * Quadrado simples de teste (fixture), NAO representa fronteiras
     * oficiais de nenhuma provincia. Coordenadas reais so devem ser
     * usadas apos importacao de fonte oficial (ver secao 4/49).
     */
    private function testSquarePolygon(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [13.0, -9.0],
                [14.0, -9.0],
                [14.0, -8.0],
                [13.0, -8.0],
                [13.0, -9.0],
            ]],
        ];
    }

    public function test_point_inside_polygon(): void
    {
        $point = new Coordinates(latitude: -8.5, longitude: 13.5);

        $this->assertTrue($this->engine->pointInPolygon($point, $this->testSquarePolygon()));
    }

    public function test_point_outside_polygon(): void
    {
        $point = new Coordinates(latitude: -20.0, longitude: 13.5);

        $this->assertFalse($this->engine->pointInPolygon($point, $this->testSquarePolygon()));
    }

    public function test_polygon_with_hole_excludes_interior_point(): void
    {
        $polygonWithHole = [
            'type' => 'Polygon',
            'coordinates' => [
                // anel exterior
                [[13.0, -9.0], [14.0, -9.0], [14.0, -8.0], [13.0, -8.0], [13.0, -9.0]],
                // buraco
                [[13.4, -8.6], [13.6, -8.6], [13.6, -8.4], [13.4, -8.4], [13.4, -8.6]],
            ],
        ];

        $pointInHole = new Coordinates(latitude: -8.5, longitude: 13.5);
        $pointOutsideHoleButInsideExterior = new Coordinates(latitude: -8.9, longitude: 13.1);

        $this->assertFalse($this->engine->pointInPolygon($pointInHole, $polygonWithHole));
        $this->assertTrue($this->engine->pointInPolygon($pointOutsideHoleButInsideExterior, $polygonWithHole));
    }

    public function test_unsupported_geometry_type_throws(): void
    {
        $this->expectException(InvalidGeometryException::class);

        $this->engine->pointInPolygon(
            new Coordinates(latitude: -8.5, longitude: 13.5),
            ['type' => 'Point', 'coordinates' => [13.5, -8.5]],
        );
    }

    public function test_is_within_radius(): void
    {
        $center = new Coordinates(latitude: -8.8390, longitude: 13.2894);
        $point = new Coordinates(latitude: -8.8395, longitude: 13.2900);

        $this->assertTrue($this->engine->isWithinRadius($point, $center, 1000));
    }
}
