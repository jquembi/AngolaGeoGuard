<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Exceptions;

final class InvalidCoordinatesException extends AngolaGeoGuardException
{
    public static function invalidLatitude(float $value): self
    {
        return new self(sprintf('Latitude invalida: %F. Deve estar entre -90 e 90.', $value));
    }

    public static function invalidLongitude(float $value): self
    {
        return new self(sprintf('Longitude invalida: %F. Deve estar entre -180 e 180.', $value));
    }

    public static function missingComponent(string $component): self
    {
        return new self(sprintf('Componente de coordenada em falta: %s.', $component));
    }
}
