<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Contracts;

use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;

/**
 * Contrato para um elo da cadeia de resolucao de localizacao
 * (IP, GPS do navegador, header confiavel, perfil, etc). Ver secao 7.
 */
interface LocationResolverInterface
{
    /**
     * Tenta resolver a localizacao a partir do contexto disponivel.
     * Deve devolver null quando este resolvedor nao tiver informacao,
     * permitindo que a cadeia continue para o proximo resolvedor.
     */
    public function resolve(mixed $context): ?LocationResult;

    public function priority(): int;

    public function name(): string;
}
