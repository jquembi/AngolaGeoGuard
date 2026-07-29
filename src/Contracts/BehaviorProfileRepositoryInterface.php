<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Contracts;

use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorProfileData;

/**
 * Contrato de persistencia do estado comportamental. Mantem
 * ThreatScorer/CountermeasureEngine completamente desacoplados de
 * Eloquent/base de dados — podem ser testados com implementacoes em
 * memoria (ver tests/Unit).
 */
interface BehaviorProfileRepositoryInterface
{
    public function find(string $subjectKey): ?BehaviorProfileData;

    public function save(BehaviorProfileData $profile, ?string $tenantId = null): void;

    /**
     * Remove perfis cujo `last_observed_at` seja anterior ao cutoff,
     * evitando crescimento ilimitado da tabela. Ver geoguard:prune.
     */
    public function pruneOlderThan(\DateTimeImmutable $cutoff): int;
}
