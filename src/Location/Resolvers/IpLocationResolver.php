<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Location\Resolvers;

use JoseQuembi\AngolaGeoGuard\Contracts\GeolocationProviderInterface;
use JoseQuembi\AngolaGeoGuard\Contracts\LocationResolverInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\Security\TrustedProxyIpResolver;

/**
 * Resolve a localizacao a partir do IP do pedido, usando o
 * TrustedProxyIpResolver para determinar o IP real de forma segura
 * (nunca confia cegamente em X-Forwarded-For) e delegando a geolocalizacao
 * propriamente dita ao GeolocationProviderInterface configurado.
 */
final class IpLocationResolver implements LocationResolverInterface
{
    public function __construct(
        private readonly GeolocationProviderInterface $provider,
        private readonly TrustedProxyIpResolver $trustedProxyResolver,
    ) {
    }

    public function name(): string
    {
        return 'ip';
    }

    public function priority(): int
    {
        return 50;
    }

    /**
     * @param  array{remote_addr: string, headers: array<string, string>}  $context
     */
    public function resolve(mixed $context): ?LocationResult
    {
        if (! is_array($context) || ! isset($context['remote_addr'])) {
            return null;
        }

        $resolved = $this->trustedProxyResolver->resolve(
            $context['remote_addr'],
            $context['headers'] ?? [],
        );

        $result = $this->provider->locate($resolved['ip']);

        if (! $result->isResolved()) {
            return null;
        }

        return $result;
    }
}
