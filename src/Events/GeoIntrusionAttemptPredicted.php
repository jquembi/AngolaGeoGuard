<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Events;

use JoseQuembi\AngolaGeoGuard\DTOs\ThreatAssessment;

/**
 * Disparado quando o ThreatScorer classifica um sujeito como
 * SUSPICIOUS ou CONFIRMED_THREAT — i.e., quando o padrao de pedidos
 * corresponde heuristicamente a uma tentativa de invasao/evasao
 * (sondagem de politicas, viagem impossivel, enumeracao, etc).
 * "Previsao" aqui e heuristica/estatistica, nao um modelo de ML — ver
 * o docblock de ThreatScorer para o disclaimer completo.
 */
final class GeoIntrusionAttemptPredicted
{
    public function __construct(
        public readonly string $subjectKey,
        public readonly ThreatAssessment $assessment,
    ) {
    }
}
