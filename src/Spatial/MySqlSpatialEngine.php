<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Spatial;

use JoseQuembi\AngolaGeoGuard\Contracts\DatabaseConnectionInterface;
use JoseQuembi\AngolaGeoGuard\Contracts\SpatialEngineInterface;
use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidGeometryException;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Motor espacial que delega calculos geometricos para MySQL 8+ ou
 * MariaDB (funcoes ST_*), usando ST_GeomFromGeoJSON quando disponivel
 * (MySQL 5.7.5+) e SRID 4326. Distancia e calculada com a formula de
 * Haversine em PHP, ja que ST_Distance_Sphere so aceita pontos, nao
 * geografia projetada como o equivalente PostGIS.
 */
final class MySqlSpatialEngine implements SpatialEngineInterface
{
    private const SRID = 4326;

    public function __construct(
        private readonly DatabaseConnectionInterface $connection,
    ) {
    }

    public function name(): string
    {
        return 'mysql';
    }

    public function pointInPolygon(Coordinates $point, array $polygon): bool
    {
        $geojson = json_encode($polygon, JSON_THROW_ON_ERROR);

        $row = $this->connection->selectOne(
            sprintf(
                'SELECT ST_Contains(ST_GeomFromGeoJSON(?, 1, %d), ST_SRID(POINT(?, ?), %d)) AS is_contained',
                self::SRID,
                self::SRID,
            ),
            [$geojson, $point->longitude, $point->latitude],
        );

        if ($row === null) {
            throw InvalidGeometryException::malformed('o motor MySQL Spatial nao devolveu resultado para ST_Contains');
        }

        return (bool) ($row['is_contained'] ?? false);
    }

    public function distanceInMeters(Coordinates $a, Coordinates $b): float
    {
        // ST_Distance_Sphere devolve metros diretamente para pontos
        // em SRID 4326, e esta disponivel tanto em MySQL como MariaDB.
        $row = $this->connection->selectOne(
            'SELECT ST_Distance_Sphere(POINT(?, ?), POINT(?, ?)) AS distance_meters',
            [$a->longitude, $a->latitude, $b->longitude, $b->latitude],
        );

        return (float) ($row['distance_meters'] ?? 0.0);
    }

    public function isWithinRadius(Coordinates $point, Coordinates $center, float $radiusMeters): bool
    {
        return $this->distanceInMeters($point, $center) <= $radiusMeters;
    }

    public function intersects(array $geometryA, array $geometryB): bool
    {
        $row = $this->connection->selectOne(
            sprintf(
                'SELECT ST_Intersects(ST_GeomFromGeoJSON(?, 1, %d), ST_GeomFromGeoJSON(?, 1, %d)) AS do_intersect',
                self::SRID,
                self::SRID,
            ),
            [
                json_encode($geometryA, JSON_THROW_ON_ERROR),
                json_encode($geometryB, JSON_THROW_ON_ERROR),
            ],
        );

        return (bool) ($row['do_intersect'] ?? false);
    }
}
