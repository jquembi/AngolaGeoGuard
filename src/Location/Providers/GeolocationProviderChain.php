<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Location\Providers;

use JoseQuembi\AngolaGeoGuard\Contracts\GeolocationProviderInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\DTOs\ProviderHealth;

/**
 * Encadeia varios provedores de geolocalizacao com failover: tenta
 * cada provedor pela ordem configurada e avanca para o seguinte se o
 * anterior falhar ou nao suportar o IP. Ver secao 8.
 */
final class GeolocationProviderChain implements GeolocationProviderInterface
{
    /**
     * @param array<GeolocationProviderInterface> $providers Em ordem de prioridade
     */
    public function __construct(
        private readonly array $providers,
        private readonly bool $failoverEnabled = true,
    ) {
    }

    public function name(): string
    {
        return 'chain('.implode(',', array_map(fn ($p) => $p->name(), $this->providers)).')';
    }

    public function supports(string $ip): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($ip)) {
                return true;
            }
        }

        return false;
    }

    public function locate(string $ip): LocationResult
    {
        foreach ($this->providers as $provider) {
            if (! $provider->supports($ip)) {
                continue;
            }

            try {
                $result = $provider->locate($ip);

                if ($result->isResolved()) {
                    return $result;
                }
            } catch (\Throwable) {
                if (! $this->failoverEnabled) {
                    break;
                }

                continue;
            }

            if (! $this->failoverEnabled) {
                break;
            }
        }

        return LocationResult::unresolved($this->name());
    }

    public function healthCheck(): ProviderHealth
    {
        $healthyCount = 0;

        foreach ($this->providers as $provider) {
            if ($provider->healthCheck()->healthy) {
                $healthyCount++;
            }
        }

        return new ProviderHealth(
            providerName: $this->name(),
            healthy: $healthyCount > 0,
            latencyMs: null,
            checkedAt: new \DateTimeImmutable(),
            message: sprintf('%d/%d provedores saudaveis', $healthyCount, count($this->providers)),
        );
    }
}
