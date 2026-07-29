<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Location\Resolvers;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\Contracts\LocationResolverInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Resolvedor manual: usa uma localizacao explicitamente fornecida
 * pelo chamador (ex.: `GeoGuard::simulate()->coordinates(...)`, ou
 * um valor guardado no perfil do utilizador). Tem confianca "verified"
 * apenas quando explicitamente marcado como tal pelo chamador — nunca
 * por defeito, para nao inflacionar artificialmente a confianca de
 * dados declarados pelo proprio utilizador.
 */
final class ManualLocationResolver implements LocationResolverInterface
{
    public function name(): string
    {
        return 'manual';
    }

    public function priority(): int
    {
        return 100;
    }

    public function resolve(mixed $context): ?LocationResult
    {
        if (! is_array($context) || (! isset($context['coordinates']) && ! isset($context['country_code']))) {
            return null;
        }

        return new LocationResult(
            coordinates: $context['coordinates'] ?? null,
            countryCode: $context['country_code'] ?? null,
            provinceSlug: $context['province_slug'] ?? null,
            municipalitySlug: $context['municipality_slug'] ?? null,
            city: $context['city'] ?? null,
            provider: $this->name(),
            confidence: ($context['verified'] ?? false) ? ConfidenceLevel::VERIFIED : ConfidenceLevel::LOW,
            resolvedAt: new DateTimeImmutable(),
            evidence: ['manual_override'],
        );
    }
}
