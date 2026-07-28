<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Location\Providers;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\Contracts\GeolocationProviderInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\DTOs\ProviderHealth;

/**
 * Provedor de geolocalizacao "nulo": sempre devolve um resultado nao
 * resolvido. Usado como default seguro quando nenhum provedor real
 * (MaxMind, API remota, base local) foi configurado pela aplicacao
 * host — para NUNCA inventar uma localizacao. Ver secao 8: o nucleo
 * nao deve obrigar a nenhum servico comercial especifico; cabe a
 * aplicacao host registar um GeolocationProviderInterface real
 * (ex.: adaptador MaxMind) no container.
 */
final class NullGeolocationProvider implements GeolocationProviderInterface
{
    public function name(): string
    {
        return 'null';
    }

    public function supports(string $ip): bool
    {
        return true;
    }

    public function locate(string $ip): LocationResult
    {
        return LocationResult::unresolved($this->name());
    }

    public function healthCheck(): ProviderHealth
    {
        return new ProviderHealth(
            providerName: $this->name(),
            healthy: false,
            latencyMs: null,
            checkedAt: new DateTimeImmutable(),
            message: 'Nenhum provedor de geolocalizacao real foi configurado.',
        );
    }
}
