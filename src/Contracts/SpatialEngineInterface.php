<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Contracts;

use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Contrato para motores de calculo espacial (memoria, PostGIS,
 * MySQL/MariaDB Spatial). Ver secao 9.
 */
interface SpatialEngineInterface
{
    /**
     * @param array $polygon GeoJSON-like geometry (Polygon ou MultiPolygon)
     */
    public function pointInPolygon(Coordinates $point, array $polygon): bool;

    public function intersects(array $geometryA, array $geometryB): bool;

    public function distanceInMeters(Coordinates $a, Coordinates $b): float;

    public function isWithinRadius(Coordinates $point, Coordinates $center, float $radiusMeters): bool;

    public function name(): string;
}
