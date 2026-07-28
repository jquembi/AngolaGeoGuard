<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Services;

use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessDecision;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;
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
 * GeoAccessPolicyEngine e aplica o FailureMode configurado quando a
 * localizacao nao pode ser determinada. Ver secoes 7, 10 e 30.
 */
final class GeoRequestEvaluator
{
    public function __construct(
        private readonly LocationResolutionPipeline $pipeline,
        private readonly GeoAccessPolicyEngine $policyEngine,
    ) {
    }

    /**
     * @param  array<string, array>  $geofenceGeometries
     */
    public function evaluate(Request $request, GeoAccessPolicyConfig $policy, array $geofenceGeometries = []): GeoAccessDecision
    {
        $context = [
            'remote_addr' => $request->ip() ?? '0.0.0.0',
            'headers' => $request->headers->all(),
        ];

        try {
            $location = $this->pipeline->resolve($context);
        } catch (LocationResolutionException) {
            $decision = $this->handleUnresolvedLocation($policy);
            $this->dispatchDecisionEvent($decision);

            return $decision;
        }

        $decision = $this->policyEngine->evaluate($location, $policy, $geofenceGeometries);
        $this->dispatchDecisionEvent($decision);

        return $decision;
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
