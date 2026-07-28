<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Exceptions;

final class InvalidGeometryException extends AngolaGeoGuardException
{
    public static function malformed(string $reason): self
    {
        return new self(sprintf('Geometria invalida: %s.', $reason));
    }

    public static function unsupportedType(string $type): self
    {
        return new self(sprintf('Tipo de geometria nao suportado: "%s". Use Polygon ou MultiPolygon.', $type));
    }
}
