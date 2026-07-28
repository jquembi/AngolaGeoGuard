<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Contracts;

use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\DTOs\ProviderHealth;

/**
 * Contrato implementado por qualquer provedor de geolocalizacao
 * (GeoIP local, MaxMind, API remota, etc). Ver secao 8 do prompt mestre.
 */
interface GeolocationProviderInterface
{
    public function locate(string $ip): LocationResult;

    public function supports(string $ip): bool;

    public function healthCheck(): ProviderHealth;

    /**
     * Nome unico do provedor, usado na cadeia de failover e nos logs.
     */
    public function name(): string;
}
