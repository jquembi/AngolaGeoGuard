<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\DTOs;

use JoseQuembi\AngolaGeoGuard\Enums\CountermeasureAction;
use JoseQuembi\AngolaGeoGuard\Enums\ThreatLevel;

/**
 * Resultado da analise comportamental de um pedido: pontuacao,
 * nivel de ameaca, sinais especificos que contribuiram, e a
 * contramedida recomendada. Ver ThreatScorer e CountermeasureEngine.
 */
final class ThreatAssessment
{
    /**
     * @param array<string> $signals Sinais especificos detetados (ex.: "impossible_travel", "province_enumeration")
     */
    public function __construct(
        public readonly int $score,
        public readonly ThreatLevel $level,
        public readonly array $signals,
        public readonly CountermeasureAction $recommendedAction,
        public readonly ?int $quarantineDurationSeconds = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'level' => $this->level->value,
            'signals' => $this->signals,
            'recommended_action' => $this->recommendedAction->value,
            'quarantine_duration_seconds' => $this->quarantineDurationSeconds,
        ];
    }
}
