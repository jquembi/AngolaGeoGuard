<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Exceptions;

final class LocationResolutionException extends AngolaGeoGuardException
{
    public static function noResolverSucceeded(): self
    {
        return new self('Nenhum resolvedor de localizacao conseguiu determinar a localizacao do pedido.');
    }

    public static function providerUnavailable(string $providerName, ?string $reason = null): self
    {
        $message = sprintf('O provedor de geolocalizacao "%s" esta indisponivel.', $providerName);

        if ($reason !== null) {
            $message .= ' Motivo: '.$reason;
        }

        return new self($message);
    }
}
