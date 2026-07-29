<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Spatial;

use JoseQuembi\AngolaGeoGuard\Contracts\DatabaseConnectionInterface;
use JoseQuembi\AngolaGeoGuard\Contracts\SpatialEngineInterface;
use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidGeometryException;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Motor espacial que delega calculos geometricos para o PostGIS,
 * usando ST_Contains / ST_Distance / ST_DWithin / ST_Intersects sobre
 * geometria enviada como GeoJSON (ST_GeomFromGeoJSON), assumindo
 * SRID 4326 (WGS 84), conforme a secao 5/9 do prompt mestre.
 *
 * Recomendado para aplicacoes de grande escala com milhoes de
 * verificacoes. Para volumes pequenos/medios, InMemorySpatialEngine
 * evita a dependencia de uma extensao PostGIS.
 */
final class PostGisSpatialEngine implements SpatialEngineInterface
{
    private const SRID = 4326;

    public function __construct(
        private readonly DatabaseConnectionInterface $connection,
    ) {
    }

    public function name(): string
    {
        return 'postgis';
    }

    public function pointInPolygon(Coordinates $point, array $polygon): bool
    {
        $geojson = json_encode($polygon, JSON_THROW_ON_ERROR);

        $row = $this->connection->selectOne(
            sprintf(
                'SELECT ST_Contains(ST_SetSRID(ST_GeomFromGeoJSON(?), %d), ST_SetSRID(ST_MakePoint(?, ?), %d)) AS is_contained',
                self::SRID,
                self::SRID,
            ),
            [$geojson, $point->longitude, $point->latitude],
        );

        if ($row === null) {
            throw InvalidGeometryException::malformed('o PostGIS nao devolveu resultado para ST_Contains');
        }

        return (bool) ($row['is_contained'] ?? false);
    }

    public function distanceInMeters(Coordinates $a, Coordinates $b): float
    {
        $row = $this->connection->selectOne(
            sprintf(
                'SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), %d)::geography, ST_SetSRID(ST_MakePoint(?, ?), %d)::geography) AS distance_meters',
                self::SRID,
                self::SRID,
            ),
            [$a->longitude, $a->latitude, $b->longitude, $b->latitude],
        );

        return (float) ($row['distance_meters'] ?? 0.0);
    }

    public function isWithinRadius(Coordinates $point, Coordinates $center, float $radiusMeters): bool
    {
        $row = $this->connection->selectOne(
            sprintf(
                'SELECT ST_DWithin(ST_SetSRID(ST_MakePoint(?, ?), %d)::geography, ST_SetSRID(ST_MakePoint(?, ?), %d)::geography, ?) AS is_within',
                self::SRID,
                self::SRID,
            ),
            [$point->longitude, $point->latitude, $center->longitude, $center->latitude, $radiusMeters],
        );

        return (bool) ($row['is_within'] ?? false);
    }

    public function intersects(array $geometryA, array $geometryB): bool
    {
        $row = $this->connection->selectOne(
            sprintf(
                'SELECT ST_Intersects(ST_SetSRID(ST_GeomFromGeoJSON(?), %d), ST_SetSRID(ST_GeomFromGeoJSON(?), %d)) AS do_intersect',
                self::SRID,
                self::SRID,
            ),
            [
                json_encode($geometryA, JSON_THROW_ON_ERROR),
                json_encode($geometryB, JSON_THROW_ON_ERROR),
            ],
        );

        if ($row === null) {
            // Recorre a triagem por bounding box do motor em memoria
            // caso a instrucao PostGIS falhe silenciosamente.
            return (new InMemorySpatialEngine())->intersects($geometryA, $geometryB);
        }

        return (bool) ($row['do_intersect'] ?? false);
    }
}
