<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Location;

use JoseQuembi\AngolaGeoGuard\Contracts\LocationResolverInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\Exceptions\LocationResolutionException;

/**
 * Executa uma cadeia configuravel de resolvedores de localizacao,
 * por ordem de prioridade (maior primeiro), parando no primeiro que
 * conseguir resolver a localizacao. Ver secao 7.
 */
final class LocationResolutionPipeline
{
    /** @var array<LocationResolverInterface> */
    private array $resolvers = [];

    public function withResolver(LocationResolverInterface $resolver): self
    {
        $clone = clone $this;
        $clone->resolvers[] = $resolver;
        $clone->resolvers = self::sortedByPriority($clone->resolvers);

        return $clone;
    }

    /**
     * @return array<LocationResolverInterface>
     */
    private static function sortedByPriority(array $resolvers): array
    {
        usort($resolvers, fn (LocationResolverInterface $a, LocationResolverInterface $b) => $b->priority() <=> $a->priority());

        return $resolvers;
    }

    public function resolve(mixed $context): LocationResult
    {
        foreach ($this->resolvers as $resolver) {
            $result = $resolver->resolve($context);

            if ($result !== null && $result->isResolved()) {
                return $result;
            }
        }

        throw LocationResolutionException::noResolverSucceeded();
    }

    /**
     * Como resolve(), mas devolve um LocationResult nao resolvido em
     * vez de lancar excecao — util quando o modo de falha for
     * OBSERVE ou ALLOW.
     */
    public function resolveOrUnresolved(mixed $context): LocationResult
    {
        try {
            return $this->resolve($context);
        } catch (LocationResolutionException) {
            return LocationResult::unresolved('pipeline');
        }
    }
}
