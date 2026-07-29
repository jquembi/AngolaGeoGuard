<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Services;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\Contracts\BehaviorProfileRepositoryInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorObservation;
use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorProfileData;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessDecision;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\DTOs\ThreatAssessment;
use JoseQuembi\AngolaGeoGuard\Enums\CountermeasureAction;
use JoseQuembi\AngolaGeoGuard\Enums\ThreatLevel;
use JoseQuembi\AngolaGeoGuard\Events\GeoCountermeasureApplied;
use JoseQuembi\AngolaGeoGuard\Events\GeoIntrusionAttemptPredicted;
use JoseQuembi\AngolaGeoGuard\Events\GeoSecurityIncidentCreated;
use JoseQuembi\AngolaGeoGuard\Security\Threat\CountermeasureEngine;
use JoseQuembi\AngolaGeoGuard\Security\Threat\ThreatScorer;

/**
 * Orquestra a deteccao comportamental: gera a chave do sujeito (hash
 * do IP, nunca em claro — ver secao 26 de privacidade), carrega o
 * perfil aprendido, calcula a nova pontuacao de ameaca, aplica
 * contramedidas quando necessario, persiste o novo estado e dispara
 * os eventos correspondentes.
 *
 * Esta e a UNICA classe desta camada que depende de Laravel
 * (config/event); ThreatScorer e CountermeasureEngine permanecem
 * puros e testaveis isoladamente.
 */
final class BehaviorTrackingService
{
    public function __construct(
        private readonly BehaviorProfileRepositoryInterface $repository,
        private readonly ThreatScorer $scorer,
        private readonly CountermeasureEngine $countermeasures,
    ) {
    }

    public static function subjectKeyFor(string $ipAddress, ?string $tenantId = null): string
    {
        $material = $tenantId !== null ? "{$tenantId}:{$ipAddress}" : $ipAddress;

        return hash('sha256', $material);
    }

    /**
     * Verifica se o sujeito esta atualmente em quarentena, SEM
     * registar uma nova observacao. Deve ser chamado antes mesmo de
     * resolver a localizacao/avaliar politicas, para rejeitar
     * pedidos de sujeitos em quarentena o mais cedo possivel (defesa
     * eficiente, estilo fail2ban).
     */
    public function isQuarantined(string $subjectKey, ?DateTimeImmutable $now = null): bool
    {
        if (! (bool) config('angola-geoguard.threat_detection.enabled', true)) {
            return false;
        }

        $now ??= new DateTimeImmutable();
        $profile = $this->repository->find($subjectKey);

        return $profile !== null && $profile->isCurrentlyQuarantined($now);
    }

    /**
     * Regista uma observacao (pedido + decisao de acesso), atualiza o
     * perfil aprendido, calcula a ameaca e aplica contramedidas.
     * Devolve o ThreatAssessment para que o chamador (GeoRequestEvaluator)
     * possa reagir (ex.: forcar CHALLENGE mesmo que a politica geografica
     * tivesse permitido o acesso).
     */
    public function record(
        string $ipAddress,
        LocationResult $location,
        GeoAccessDecision $decision,
        ?string $tenantId = null,
        ?DateTimeImmutable $now = null,
    ): ThreatAssessment {
        if (! (bool) config('angola-geoguard.threat_detection.enabled', true)) {
            return new ThreatAssessment(0, ThreatLevel::NONE, [], CountermeasureAction::NONE);
        }

        $now ??= new DateTimeImmutable();
        $subjectKey = self::subjectKeyFor($ipAddress, $tenantId);

        $profile = $this->repository->find($subjectKey) ?? BehaviorProfileData::fresh($subjectKey, $now);

        $observation = new BehaviorObservation(
            subjectKey: $subjectKey,
            observedAt: $now,
            coordinates: $location->coordinates,
            countryCode: $location->countryCode,
            provinceSlug: $location->provinceSlug,
            isVpn: $location->isVpn,
            isProxy: $location->isProxy,
            isTor: $location->isTor,
            allowed: $decision->allowed,
        );

        $assessment = $this->scorer->score($profile, $observation);
        $violationOccurred = $assessment->recommendedAction->severity() >= CountermeasureAction::CHALLENGE->severity();

        $nextProfile = $this->scorer->nextProfile($profile, $observation, $violationOccurred);

        if ($assessment->recommendedAction === CountermeasureAction::QUARANTINE) {
            $applied = $this->countermeasures->apply($assessment, $nextProfile, $now);
            $assessment = $applied['assessment'];
            $nextProfile = $applied['profile'];
        }

        $this->repository->save($nextProfile, $tenantId);

        $this->dispatchEvents($subjectKey, $assessment, $nextProfile);

        return $assessment;
    }

    private function dispatchEvents(string $subjectKey, ThreatAssessment $assessment, BehaviorProfileData $profile): void
    {
        if (! function_exists('event')) {
            return;
        }

        if ($assessment->level->weight() >= ThreatLevel::SUSPICIOUS->weight()) {
            event(new GeoIntrusionAttemptPredicted($subjectKey, $assessment));
        }

        if ($assessment->recommendedAction === CountermeasureAction::NONE) {
            return;
        }

        event(new GeoCountermeasureApplied(
            subjectKey: $subjectKey,
            action: $assessment->recommendedAction,
            quarantineDurationSeconds: $assessment->quarantineDurationSeconds,
            violationCount: $profile->violationCount,
        ));

        if ($assessment->recommendedAction === CountermeasureAction::QUARANTINE) {
            event(new GeoSecurityIncidentCreated(
                type: 'behavioral_quarantine',
                description: sprintf(
                    'Sujeito colocado em quarentena por %d segundos apos %d violacao(oes) anteriores. Sinais: %s',
                    $assessment->quarantineDurationSeconds ?? 0,
                    $profile->violationCount,
                    implode(', ', $assessment->signals),
                ),
                context: $assessment->toArray(),
            ));
        }
    }
}
