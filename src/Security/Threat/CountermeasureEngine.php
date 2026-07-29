<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Security\Threat;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorProfileData;
use JoseQuembi\AngolaGeoGuard\DTOs\ThreatAssessment;
use JoseQuembi\AngolaGeoGuard\Enums\CountermeasureAction;

/**
 * Decide a duracao efetiva de uma quarentena, escalando
 * progressivamente para sujeitos reincidentes — o mesmo principio
 * usado por ferramentas como fail2ban. Um primeiro incidente resulta
 * numa quarentena curta; violacoes repetidas (mesmo apos a janela
 * comportamental reiniciar) aumentam a duracao exponencialmente ate
 * um teto configuravel. Esta e a componente "memoria" do sistema:
 * `violationCount` no perfil nunca e reiniciado pela janela deslizante.
 */
final class CountermeasureEngine
{
    public function __construct(
        private readonly int $baseQuarantineSeconds = 900, // 15 minutos
        private readonly int $maxQuarantineSeconds = 86400, // 24 horas
        private readonly float $escalationFactor = 2.0,
    ) {
    }

    /**
     * Aplica a decisao de contramedida ao perfil, devolvendo o novo
     * ThreatAssessment (com duracao de quarentena calculada, se
     * aplicavel) e o perfil atualizado com `quarantinedUntil` definido.
     *
     * @return array{assessment: ThreatAssessment, profile: BehaviorProfileData}
     */
    public function apply(ThreatAssessment $assessment, BehaviorProfileData $profile, DateTimeImmutable $now): array
    {
        if ($assessment->recommendedAction !== CountermeasureAction::QUARANTINE) {
            return ['assessment' => $assessment, 'profile' => $profile];
        }

        $durationSeconds = $this->calculateEscalatedDuration($profile->violationCount);
        $quarantinedUntil = $now->modify(sprintf('+%d seconds', $durationSeconds));

        $newAssessment = new ThreatAssessment(
            score: $assessment->score,
            level: $assessment->level,
            signals: $assessment->signals,
            recommendedAction: $assessment->recommendedAction,
            quarantineDurationSeconds: $durationSeconds,
        );

        $newProfile = new BehaviorProfileData(
            subjectKey: $profile->subjectKey,
            windowStartedAt: $profile->windowStartedAt,
            windowRequestCount: $profile->windowRequestCount,
            windowDeniedCount: $profile->windowDeniedCount,
            distinctProvincesInWindow: $profile->distinctProvincesInWindow,
            distinctCountriesInWindow: $profile->distinctCountriesInWindow,
            lastObservedAt: $profile->lastObservedAt,
            lastCoordinates: $profile->lastCoordinates,
            lastIsVpn: $profile->lastIsVpn,
            lastIsProxy: $profile->lastIsProxy,
            lastIsTor: $profile->lastIsTor,
            flagChangeCountInWindow: $profile->flagChangeCountInWindow,
            ewmaIntervalSeconds: $profile->ewmaIntervalSeconds,
            violationCount: $profile->violationCount,
            quarantinedUntil: $quarantinedUntil,
            lastQuarantineDurationSeconds: $durationSeconds,
        );

        return ['assessment' => $newAssessment, 'profile' => $newProfile];
    }

    /**
     * Duracao = base * (fator ^ numero_de_violacoes_anteriores),
     * limitada ao teto configurado. Ex. com base=15min, fator=2:
     * 1a violacao -> 15min, 2a -> 30min, 3a -> 1h, 4a -> 2h, ... ate 24h.
     */
    public function calculateEscalatedDuration(int $previousViolationCount): int
    {
        $duration = (int) round($this->baseQuarantineSeconds * ($this->escalationFactor ** max(0, $previousViolationCount)));

        return min($duration, $this->maxQuarantineSeconds);
    }
}
