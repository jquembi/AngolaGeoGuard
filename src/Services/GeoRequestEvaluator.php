<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Services;

use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessDecision;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;
use JoseQuembi\AngolaGeoGuard\Enums\CountermeasureAction;
use JoseQuembi\AngolaGeoGuard\Enums\DecisionReasonCode;
use JoseQuembi\AngolaGeoGuard\Enums\FailureMode;
use JoseQuembi\AngolaGeoGuard\Enums\RiskLevel;
use JoseQuembi\AngolaGeoGuard\Events\GeoAccessAllowed;
use JoseQuembi\AngolaGeoGuard\Events\GeoAccessDenied;
use JoseQuembi\AngolaGeoGuard\Events\GeoAccessRequiresVerification;
use JoseQuembi\AngolaGeoGuard\Exceptions\LocationResolutionException;
use JoseQuembi\AngolaGeoGuard\Location\LocationResolutionPipeline;

/**
 * Servico de mais alto nivel usado pelo middleware Laravel: resolve a
 * localizacao do pedido HTTP, aplica a politica indicada atraves do
 * GeoAccessPolicyEngine, aplica o FailureMode configurado quando a
 * localizacao nao pode ser determinada, e integra a camada de
 * deteccao comportamental (BehaviorTrackingService): sujeitos em
 * quarentena sao rejeitados antes mesmo de resolver a localizacao;
 * apos cada decisao, o comportamento e registado para aprendizagem
 * continua e possivel escalonamento de contramedidas. Ver secoes 7,
 * 10, 13, 30 e 46.
 */
final class GeoRequestEvaluator
{
    public function __construct(
        private readonly LocationResolutionPipeline $pipeline,
        private readonly GeoAccessPolicyEngine $policyEngine,
        private readonly ?BehaviorTrackingService $behaviorTracking = null,
    ) {
    }

    /**
     * @param array<string, array> $geofenceGeometries
     */
    public function evaluate(Request $request, GeoAccessPolicyConfig $policy, array $geofenceGeometries = []): GeoAccessDecision
    {
        $ipAddress = $request->ip() ?? '0.0.0.0';
        $tenantId = $request->attributes->get('geo_tenant_id');

        if ($this->behaviorTracking !== null) {
            $subjectKey = BehaviorTrackingService::subjectKeyFor($ipAddress, $tenantId);

            if ($this->behaviorTracking->isQuarantined($subjectKey)) {
                $decision = $this->quarantinedDecision($policy);
                $this->dispatchDecisionEvent($decision);

                return $decision;
            }
        }

        $context = [
            'remote_addr' => $ipAddress,
            'headers' => $request->headers->all(),
        ];

        try {
            $location = $this->pipeline->resolve($context);
        } catch (LocationResolutionException) {
            $decision = $this->handleUnresolvedLocation($policy);
            $this->dispatchDecisionEvent($decision);
            $this->behaviorTracking?->record($ipAddress, LocationResult::unresolved('pipeline'), $decision, $tenantId);

            return $decision;
        }

        $decision = $this->policyEngine->evaluate($location, $policy, $geofenceGeometries);
        $decision = $this->applyBehaviorTracking($ipAddress, $location, $decision, $tenantId, $policy);
        $this->dispatchDecisionEvent($decision);

        return $decision;
    }

    /**
     * Regista a observacao na camada comportamental e, se a
     * contramedida recomendada for CHALLENGE ou superior, escala a
     * decisao atual imediatamente (em vez de esperar pelo proximo
     * pedido) — a quarentena continua a ser aplicada preventivamente
     * nos pedidos seguintes via isQuarantined().
     */
    private function applyBehaviorTracking(string $ipAddress, LocationResult $location, GeoAccessDecision $decision, ?string $tenantId, GeoAccessPolicyConfig $policy): GeoAccessDecision
    {
        if ($this->behaviorTracking === null) {
            return $decision;
        }

        $assessment = $this->behaviorTracking->record($ipAddress, $location, $decision, $tenantId);

        if ($assessment->recommendedAction->severity() < CountermeasureAction::CHALLENGE->severity()) {
            return $decision;
        }

        if (! $decision->allowed) {
            // Ja negado pela politica geografica; nao ha necessidade
            // de "melhorar" a negacao, mas os sinais comportamentais
            // ainda sao uteis para os avisos/evidencias do registo.
            return $decision;
        }

        $reasonCode = $assessment->recommendedAction === CountermeasureAction::QUARANTINE
            ? DecisionReasonCode::ACCESS_DENIED_QUARANTINED
            : DecisionReasonCode::ACCESS_REQUIRES_VERIFICATION;

        return new GeoAccessDecision(
            allowed: false,
            reasonCode: $reasonCode,
            reason: 'Comportamento suspeito detetado neste pedido; e necessaria verificacao adicional ou o acesso foi temporariamente suspenso.',
            location: $decision->location,
            policyIdentifier: $policy->identifier,
            confidence: $decision->confidence,
            risk: $decision->risk,
            evidence: [...$decision->evidence, ...$assessment->signals],
            warnings: $decision->warnings,
            appliedExceptions: $decision->appliedExceptions,
            decidedAt: new \DateTimeImmutable(),
        );
    }

    private function quarantinedDecision(GeoAccessPolicyConfig $policy): GeoAccessDecision
    {
        return new GeoAccessDecision(
            allowed: false,
            reasonCode: DecisionReasonCode::ACCESS_DENIED_QUARANTINED,
            reason: 'Este sujeito esta temporariamente em quarentena devido a comportamento suspeito detetado anteriormente.',
            location: null,
            policyIdentifier: $policy->identifier,
            confidence: ConfidenceLevel::VERIFIED,
            risk: RiskLevel::CRITICAL,
            evidence: ['behavioral_quarantine_active'],
            warnings: [],
            appliedExceptions: [],
            decidedAt: new \DateTimeImmutable(),
        );
    }

    private function dispatchDecisionEvent(GeoAccessDecision $decision): void
    {
        if (! function_exists('event')) {
            return;
        }

        if ($decision->reasonCode === \JoseQuembi\AngolaGeoGuard\Enums\DecisionReasonCode::ACCESS_REQUIRES_VERIFICATION) {
            event(new GeoAccessRequiresVerification($decision));

            return;
        }

        event($decision->allowed ? new GeoAccessAllowed($decision) : new GeoAccessDenied($decision));
    }

    private function handleUnresolvedLocation(GeoAccessPolicyConfig $policy): GeoAccessDecision
    {
        $failureMode = FailureMode::from((string) config('angola-geoguard.failure_mode', FailureMode::DENY->value));

        $unresolved = LocationResult::unresolved('pipeline');

        return match ($failureMode) {
            FailureMode::ALLOW => new GeoAccessDecision(
                allowed: true,
                reasonCode: DecisionReasonCode::ACCESS_ALLOWED_EXCEPTION,
                reason: 'Localizacao nao resolvida; permitido por FailureMode::ALLOW.',
                location: $unresolved,
                policyIdentifier: $policy->identifier,
                confidence: ConfidenceLevel::VERY_LOW,
                risk: RiskLevel::HIGH,
                evidence: [],
                warnings: ['Decisao tomada sob FailureMode::ALLOW sem localizacao resolvida.'],
                appliedExceptions: [],
                decidedAt: new \DateTimeImmutable(),
            ),
            FailureMode::OBSERVE => new GeoAccessDecision(
                allowed: true,
                reasonCode: DecisionReasonCode::ACCESS_ALLOWED_EXCEPTION,
                reason: 'Localizacao nao resolvida; modo de observacao nao bloqueia.',
                location: $unresolved,
                policyIdentifier: $policy->identifier,
                confidence: ConfidenceLevel::VERY_LOW,
                risk: RiskLevel::MEDIUM,
                evidence: [],
                warnings: ['Decisao registada em modo OBSERVE; nao ha bloqueio.'],
                appliedExceptions: [],
                decidedAt: new \DateTimeImmutable(),
            ),
            FailureMode::CHALLENGE => new GeoAccessDecision(
                allowed: false,
                reasonCode: DecisionReasonCode::ACCESS_REQUIRES_VERIFICATION,
                reason: 'Localizacao nao resolvida; e necessaria verificacao adicional.',
                location: $unresolved,
                policyIdentifier: $policy->identifier,
                confidence: ConfidenceLevel::VERY_LOW,
                risk: RiskLevel::MEDIUM,
                evidence: [],
                warnings: [],
                appliedExceptions: [],
                decidedAt: new \DateTimeImmutable(),
            ),
            FailureMode::DENY => new GeoAccessDecision(
                allowed: false,
                reasonCode: DecisionReasonCode::ACCESS_DENIED_UNRESOLVED_LOCATION,
                reason: 'Nao foi possivel determinar a localizacao do pedido.',
                location: $unresolved,
                policyIdentifier: $policy->identifier,
                confidence: ConfidenceLevel::VERY_LOW,
                risk: RiskLevel::MEDIUM,
                evidence: [],
                warnings: [],
                appliedExceptions: [],
                decidedAt: new \DateTimeImmutable(),
            ),
        };
    }
}
