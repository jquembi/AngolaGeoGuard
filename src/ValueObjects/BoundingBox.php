<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\ValueObjects;

/**
 * Caixa delimitadora retangular usada para triagem rapida antes de
 * um calculo point-in-polygon mais caro.
 */
final class BoundingBox
{
    public function __construct(
        public readonly float $minLatitude,
        public readonly float $maxLatitude,
        public readonly float $minLongitude,
        public readonly float $maxLongitude,
    ) {
    }

    public static function fromPolygon(array $geometry): self
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? [];

        $rings = match ($type) {
            'Polygon' => [$coordinates[0] ?? []],
            'MultiPolygon' => array_map(fn ($polygon) => $polygon[0] ?? [], $coordinates),
            default => [],
        };

        $lats = [];
        $lngs = [];

        foreach ($rings as $ring) {
            foreach ($ring as $pair) {
                $lngs[] = $pair[0];
                $lats[] = $pair[1];
            }
        }

        return new self(
            minLatitude: empty($lats) ? -90.0 : min($lats),
            maxLatitude: empty($lats) ? 90.0 : max($lats),
            minLongitude: empty($lngs) ? -180.0 : min($lngs),
            maxLongitude: empty($lngs) ? 180.0 : max($lngs),
        );
    }

    public function contains(Coordinates $point): bool
    {
        return $point->latitude >= $this->minLatitude
            && $point->latitude <= $this->maxLatitude
            && $point->longitude >= $this->minLongitude
            && $point->longitude <= $this->maxLongitude;
    }

    public function toArray(): array
    {
        return [
            'min_latitude' => $this->minLatitude,
            'max_latitude' => $this->maxLatitude,
            'min_longitude' => $this->minLongitude,
            'max_longitude' => $this->maxLongitude,
        ];
    }
}
