<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Spatial;

use JoseQuembi\AngolaGeoGuard\Contracts\SpatialEngineInterface;
use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidGeometryException;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Motor espacial em memoria, sem dependencias externas. Adequado para
 * volumes pequenos/medios. Aplicacoes de grande escala podem trocar
 * esta implementacao por PostGIS/MySQL Spatial via
 * config('angola-geoguard.spatial.engine').
 *
 * Aceita geometrias no formato GeoJSON (Polygon ou MultiPolygon):
 * ['type' => 'Polygon', 'coordinates' => [[[lng, lat], ...]]]
 */
final class InMemorySpatialEngine implements SpatialEngineInterface
{
    public function name(): string
    {
        return 'memory';
    }

    public function pointInPolygon(Coordinates $point, array $polygon): bool
    {
        $type = $polygon['type'] ?? null;
        $coordinates = $polygon['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            throw InvalidGeometryException::malformed('propriedade "coordinates" em falta');
        }

        return match ($type) {
            'Polygon' => $this->pointInPolygonRings($point, $coordinates),
            'MultiPolygon' => $this->pointInMultiPolygon($point, $coordinates),
            default => throw InvalidGeometryException::unsupportedType((string) $type),
        };
    }

    public function distanceInMeters(Coordinates $a, Coordinates $b): float
    {
        return $a->distanceTo($b);
    }

    public function isWithinRadius(Coordinates $point, Coordinates $center, float $radiusMeters): bool
    {
        return $point->isWithinRadiusOf($center, $radiusMeters);
    }

    public function intersects(array $geometryA, array $geometryB): bool
    {
        // Verificacao simplificada baseada em bounding box, suficiente
        // para triagem rapida. Uma verificacao geometrica completa
        // (Sutherland-Hodgman / Weiler-Atherton) pode ser adicionada
        // como estrategia alternativa em Spatial/ na Fase 3.
        $boxA = $this->boundingBoxOf($geometryA);
        $boxB = $this->boundingBoxOf($geometryB);

        return ! (
            $boxA['max_lng'] < $boxB['min_lng']
            || $boxA['min_lng'] > $boxB['max_lng']
            || $boxA['max_lat'] < $boxB['min_lat']
            || $boxA['min_lat'] > $boxB['max_lat']
        );
    }

    /**
     * Ray-casting: suporta poligonos com buracos (rings[0] = exterior,
     * rings[1..n] = buracos a subtrair).
     */
    private function pointInPolygonRings(Coordinates $point, array $rings): bool
    {
        if (empty($rings)) {
            throw InvalidGeometryException::malformed('poligono sem aneis de coordenadas');
        }

        $insideExterior = $this->rayCast($point, $rings[0]);

        if (! $insideExterior) {
            return false;
        }

        for ($i = 1; $i < count($rings); $i++) {
            if ($this->rayCast($point, $rings[$i])) {
                // O ponto esta dentro de um buraco -> fora do poligono.
                return false;
            }
        }

        return true;
    }

    private function pointInMultiPolygon(Coordinates $point, array $polygons): bool
    {
        foreach ($polygons as $rings) {
            if ($this->pointInPolygonRings($point, $rings)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Algoritmo classico de ray-casting (even-odd rule).
     *
     * @param  array<array{0: float, 1: float}>  $ring  [[lng, lat], ...]
     */
    private function rayCast(Coordinates $point, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$lngI, $latI] = $ring[$i];
            [$lngJ, $latJ] = $ring[$j];

            $intersects = (($latI > $point->latitude) !== ($latJ > $point->latitude))
                && ($point->longitude < ($lngJ - $lngI) * ($point->latitude - $latI) / ($latJ - $latI) + $lngI);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function boundingBoxOf(array $geometry): array
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? [];

        $flat = match ($type) {
            'Polygon' => $coordinates[0] ?? [],
            'MultiPolygon' => array_merge(...array_map(fn ($polygon) => $polygon[0] ?? [], $coordinates)),
            default => throw InvalidGeometryException::unsupportedType((string) $type),
        };

        $lngs = array_column($flat, 0);
        $lats = array_column($flat, 1);

        if (empty($lngs) || empty($lats)) {
            throw InvalidGeometryException::malformed('geometria sem coordenadas validas');
        }

        return [
            'min_lng' => min($lngs),
            'max_lng' => max($lngs),
            'min_lat' => min($lats),
            'max_lat' => max($lats),
        ];
    }
}
