<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\ValueObjects;

use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidCoordinatesException;

/**
 * Value Object imutavel representando um par de coordenadas WGS84 (EPSG:4326).
 */
final class Coordinates
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
        if ($this->latitude < -90.0 || $this->latitude > 90.0) {
            throw InvalidCoordinatesException::invalidLatitude($this->latitude);
        }

        if ($this->longitude < -180.0 || $this->longitude > 180.0) {
            throw InvalidCoordinatesException::invalidLongitude($this->longitude);
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            latitude: (float) ($data['latitude'] ?? $data['lat'] ?? throw InvalidCoordinatesException::missingComponent('latitude')),
            longitude: (float) ($data['longitude'] ?? $data['lng'] ?? $data['lon'] ?? throw InvalidCoordinatesException::missingComponent('longitude')),
        );
    }

    public function equals(self $other, float $epsilon = 0.0000001): bool
    {
        return abs($this->latitude - $other->latitude) < $epsilon
            && abs($this->longitude - $other->longitude) < $epsilon;
    }

    /**
     * Distancia em metros usando a formula de Haversine.
     */
    public function distanceTo(self $other): float
    {
        $earthRadiusMeters = 6_371_000.0;

        $latFrom = deg2rad($this->latitude);
        $latTo = deg2rad($other->latitude);
        $latDelta = deg2rad($other->latitude - $this->latitude);
        $lonDelta = deg2rad($other->longitude - $this->longitude);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }

    public function isWithinRadiusOf(self $center, float $radiusMeters): bool
    {
        return $this->distanceTo($center) <= $radiusMeters;
    }

    /**
     * Arredonda a coordenada para reduzir a precisao armazenada
     * (protecao de privacidade, ver secao 26 do prompt mestre).
     */
    public function roundedTo(int $decimals = 3): self
    {
        return new self(
            latitude: round($this->latitude, $decimals),
            longitude: round($this->longitude, $decimals),
        );
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    public function __toString(): string
    {
        return sprintf('%F,%F', $this->latitude, $this->longitude);
    }
}
