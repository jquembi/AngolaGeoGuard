<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Exceptions;

final class GeoPolicyNotFoundException extends AngolaGeoGuardException
{
    public static function forIdentifier(string $identifier): self
    {
        return new self(sprintf('Politica geografica nao encontrada: "%s".', $identifier));
    }
}
