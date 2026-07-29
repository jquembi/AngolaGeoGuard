<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Services;

use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\Contracts\TenantContextInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessDecision;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;
use JoseQuembi\AngolaGeoGuard\Exceptions\GeoPolicyNotFoundException;
use JoseQuembi\AngolaGeoGuard\Models\GeoAccessExceptionGrant;
use JoseQuembi\AngolaGeoGuard\Models\GeoAccessPolicy;
use JoseQuembi\AngolaGeoGuard\Models\Geofence;

/**
 * API fluida usada atraves da Facade GeoGuard. Ver secao 19.
 *
 * Exemplo:
 *   GeoGuard::request($request)->country('AO')->province('huila')
 *       ->minimumConfidence('medium')->denyVpn()->evaluate();
 */
final class GeoAccessRequestBuilder
{
    private ?AccessMode $mode = null;

    private array $allowedCountries = ['AO'];

    private array $allowedProvinces = [];

    private array $blockedProvinces = [];

    private ConfidenceLevel $minimumConfidence = ConfidenceLevel::MEDIUM;

    private bool $blockVpn = false;

    private bool $blockProxy = false;

    private bool $blockTor = true;

    private ?string $userId = null;

    private ?string $policySlug = null;

    public function __construct(
        private readonly GeoRequestEvaluator $evaluator,
        private readonly Request $request,
    ) {
    }

    public function country(string $isoCode): self
    {
        $this->mode ??= AccessMode::ANGOLA_ONLY;
        $this->allowedCountries = [$isoCode];

        return $this;
    }

    public function province(string $slug): self
    {
        $this->mode = AccessMode::PROVINCE_ONLY;
        $this->allowedProvinces = [$slug];

        return $this;
    }

    /**
     * @param array<string> $slugs
     */
    public function provinces(array $slugs): self
    {
        $this->mode = AccessMode::MULTIPLE_PROVINCES;
        $this->allowedProvinces = $slugs;

        return $this;
    }

    /**
     * @param array<string> $slugs
     */
    public function blockProvinces(array $slugs): self
    {
        $this->mode ??= AccessMode::BLOCKLIST;
        $this->blockedProvinces = $slugs;

        return $this;
    }

    public function minimumConfidence(string|ConfidenceLevel $level): self
    {
        $this->minimumConfidence = $level instanceof ConfidenceLevel ? $level : ConfidenceLevel::fromString($level);

        return $this;
    }

    public function denyVpn(): self
    {
        $this->blockVpn = true;

        return $this;
    }

    public function denyProxy(): self
    {
        $this->blockProxy = true;

        return $this;
    }

    public function allowTor(): self
    {
        $this->blockTor = false;

        return $this;
    }

    public function forUser(int|string $userId): self
    {
        $this->userId = (string) $userId;

        return $this;
    }

    public function forTenant(TenantContextInterface $tenant): self
    {
        $this->request->attributes->set('geo_tenant_id', $tenant->tenantId());

        return $this;
    }

    public function usingPolicy(string $slug): self
    {
        $this->policySlug = $slug;

        return $this;
    }

    public function evaluate(): GeoAccessDecision
    {
        // 1. Politica persistida por slug tem prioridade quando indicada.
        if ($this->policySlug !== null) {
            $policyModel = GeoAccessPolicy::query()
                ->where('slug', $this->policySlug)
                ->where('is_active', true)
                ->first();

            if ($policyModel === null) {
                throw GeoPolicyNotFoundException::forIdentifier($this->policySlug);
            }

            $decision = $this->evaluateWithPolicyModel($policyModel);
        } else {
            $decision = $this->evaluator->evaluate($this->request, $this->buildAdHocPolicy());
        }

        // 2. Excecoes ativas do utilizador podem reverter uma negacao.
        if ($decision->denied() && $this->userId !== null) {
            $exception = $this->findApplicableException($decision->policyIdentifier);

            if ($exception !== null) {
                $exception->recordUsage();

                return new GeoAccessDecision(
                    allowed: true,
                    reasonCode: \JoseQuembi\AngolaGeoGuard\Enums\DecisionReasonCode::ACCESS_ALLOWED_EXCEPTION,
                    reason: 'Acesso permitido por excecao temporaria: '.$exception->reason,
                    location: $decision->location,
                    policyIdentifier: $decision->policyIdentifier,
                    confidence: $decision->confidence,
                    risk: $decision->risk,
                    evidence: $decision->evidence,
                    warnings: $decision->warnings,
                    appliedExceptions: [$exception->uuid],
                    decidedAt: new \DateTimeImmutable(),
                );
            }
        }

        return $decision;
    }

    private function evaluateWithPolicyModel(GeoAccessPolicy $policyModel): GeoAccessDecision
    {
        $geofenceGeometries = $policyModel->allowed_geofences
            ? Geofence::query()->whereIn('slug', $policyModel->allowed_geofences)->pluck('geometry', 'slug')->all()
            : [];

        return $this->evaluator->evaluate($this->request, $policyModel->toConfig(), $geofenceGeometries);
    }

    private function buildAdHocPolicy(): GeoAccessPolicyConfig
    {
        return new GeoAccessPolicyConfig(
            identifier: 'fluent-request',
            mode: $this->mode ?? AccessMode::GLOBAL,
            allowedCountries: $this->allowedCountries,
            allowedProvinces: $this->allowedProvinces,
            blockedProvinces: $this->blockedProvinces,
            minimumConfidence: $this->minimumConfidence,
            blockVpn: $this->blockVpn,
            blockProxy: $this->blockProxy,
            blockTor: $this->blockTor,
        );
    }

    private function findApplicableException(?string $policyIdentifier): ?GeoAccessExceptionGrant
    {
        $exceptions = GeoAccessExceptionGrant::query()
            ->where('user_id', $this->userId)
            ->where('status', 'active')
            ->get();

        foreach ($exceptions as $exception) {
            if ($exception->isCurrentlyValid()) {
                return $exception;
            }
        }

        return null;
    }
}
