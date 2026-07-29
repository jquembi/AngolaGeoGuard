<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Services;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\Contracts\SpatialEngineInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessDecision;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\DTOs\LocationResult;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Enums\ConfidenceLevel;
use JoseQuembi\AngolaGeoGuard\Enums\DecisionReasonCode;
use JoseQuembi\AngolaGeoGuard\Enums\RiskLevel;

/**
 * Motor de decisao geografica. Framework-agnostic: nao acede a base
 * de dados nem ao Eloquent — recebe a politica ja resolvida
 * (GeoAccessPolicyConfig), a localizacao ja resolvida (LocationResult)
 * e, para AccessMode::CUSTOM_GEOFENCE, as geometrias dos geofences
 * permitidos ja carregadas pelo chamador. Ver secao 10.
 */
final class GeoAccessPolicyEngine
{
    public function __construct(
        private readonly SpatialEngineInterface $spatialEngine,
    ) {
    }

    /**
     * @param array<string, array> $geofenceGeometries slug => geometria GeoJSON (para CUSTOM_GEOFENCE)
     */
    public function evaluate(
        LocationResult $location,
        GeoAccessPolicyConfig $policy,
        array $geofenceGeometries = [],
    ): GeoAccessDecision {
        $startedAt = microtime(true);
        $warnings = [];
        $evidence = [];

        $risk = $this->assessRisk($location, $warnings, $evidence);

        // 1. Verificacoes de seguranca (VPN/Proxy/Tor/Datacenter) tem
        // prioridade — aplicam-se independentemente do modo, incluindo GLOBAL.
        if ($policy->blockTor && $location->isTor) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_TOR, 'Acesso via rede Tor bloqueado pela politica.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        if ($policy->blockVpn && $location->isVpn) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_VPN, 'Acesso via VPN bloqueado pela politica.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        if ($policy->blockProxy && $location->isProxy) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_PROXY, 'Acesso via proxy bloqueado pela politica.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        if ($policy->blockDatacenter && $location->isDatacenter) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_DATACENTER, 'Acesso a partir de IP de datacenter bloqueado pela politica.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        // 2. Modo global nao exige localizacao resolvida nem confianca minima.
        if ($policy->mode === AccessMode::GLOBAL) {
            return $this->allow(DecisionReasonCode::ACCESS_ALLOWED_GLOBAL, 'Acesso global permitido.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        // 3. Para todos os outros modos, a localizacao deve estar resolvida
        // com confianca minima suficiente.
        if (! $location->isResolved()) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_UNRESOLVED_LOCATION, 'Nao foi possivel determinar a localizacao do pedido.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        if (! $location->confidence->meetsMinimum($policy->minimumConfidence)) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_LOW_CONFIDENCE, 'O nivel de confianca da localizacao e insuficiente para esta politica.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        return match ($policy->mode) {
            AccessMode::ANGOLA_ONLY => $this->evaluateCountry($location, $policy, $risk, $evidence, $warnings, $startedAt),
            AccessMode::PROVINCE_ONLY, AccessMode::MULTIPLE_PROVINCES, AccessMode::ALLOWLIST => $this->evaluateProvinceAllowlist($location, $policy, $risk, $evidence, $warnings, $startedAt),
            AccessMode::BLOCKLIST => $this->evaluateBlocklist($location, $policy, $risk, $evidence, $warnings, $startedAt),
            AccessMode::CUSTOM_GEOFENCE => $this->evaluateGeofence($location, $policy, $geofenceGeometries, $risk, $evidence, $warnings, $startedAt),
            AccessMode::HYBRID => $this->evaluateHybrid($location, $policy, $risk, $evidence, $warnings, $startedAt),
        };
    }

    private function evaluateCountry(LocationResult $location, GeoAccessPolicyConfig $policy, RiskLevel $risk, array $evidence, array $warnings, float $startedAt): GeoAccessDecision
    {
        if ($location->countryCode !== null && in_array($location->countryCode, $policy->allowedCountries, true)) {
            return $this->allow(DecisionReasonCode::ACCESS_ALLOWED_COUNTRY, 'Acesso permitido: pais autorizado.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        return $this->deny(DecisionReasonCode::ACCESS_DENIED_COUNTRY, 'Acesso negado: pais nao autorizado por esta politica.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
    }

    private function evaluateProvinceAllowlist(LocationResult $location, GeoAccessPolicyConfig $policy, RiskLevel $risk, array $evidence, array $warnings, float $startedAt): GeoAccessDecision
    {
        if ($location->countryCode === null || ! in_array($location->countryCode, $policy->allowedCountries, true)) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_COUNTRY, 'Acesso negado: pais nao autorizado por esta politica.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        if ($location->provinceSlug !== null && in_array($location->provinceSlug, $policy->allowedProvinces, true)) {
            return $this->allow(DecisionReasonCode::ACCESS_ALLOWED_PROVINCE, 'Acesso permitido: provincia autorizada.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        return $this->deny(DecisionReasonCode::ACCESS_DENIED_PROVINCE, 'Acesso negado: provincia nao autorizada por esta politica.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
    }

    private function evaluateBlocklist(LocationResult $location, GeoAccessPolicyConfig $policy, RiskLevel $risk, array $evidence, array $warnings, float $startedAt): GeoAccessDecision
    {
        if ($location->provinceSlug !== null && in_array($location->provinceSlug, $policy->blockedProvinces, true)) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_PROVINCE, 'Acesso negado: provincia bloqueada por esta politica.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        return $this->allow(DecisionReasonCode::ACCESS_ALLOWED_COUNTRY, 'Acesso permitido: provincia nao consta da lista de bloqueio.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
    }

    /**
     * @param array<string, array> $geofenceGeometries
     */
    private function evaluateGeofence(LocationResult $location, GeoAccessPolicyConfig $policy, array $geofenceGeometries, RiskLevel $risk, array $evidence, array $warnings, float $startedAt): GeoAccessDecision
    {
        if ($location->coordinates === null) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_UNRESOLVED_LOCATION, 'Coordenadas necessarias para avaliar geofence personalizado nao disponiveis.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        foreach ($policy->allowedGeofenceSlugs as $slug) {
            if (! isset($geofenceGeometries[$slug])) {
                $warnings[] = sprintf('Geofence "%s" referenciado pela politica mas geometria nao foi fornecida.', $slug);

                continue;
            }

            if ($this->spatialEngine->pointInPolygon($location->coordinates, $geofenceGeometries[$slug])) {
                $evidence[] = sprintf('point_in_geofence:%s', $slug);

                return $this->allow(DecisionReasonCode::ACCESS_ALLOWED_PROVINCE, sprintf('Acesso permitido: dentro do geofence "%s".', $slug), $location, $policy, $risk, $evidence, $warnings, $startedAt);
            }
        }

        return $this->deny(DecisionReasonCode::ACCESS_DENIED_OUTSIDE_GEOFENCE, 'Acesso negado: fora de todos os geofences permitidos.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
    }

    private function evaluateHybrid(LocationResult $location, GeoAccessPolicyConfig $policy, RiskLevel $risk, array $evidence, array $warnings, float $startedAt): GeoAccessDecision
    {
        // Blocklist tem prioridade sobre allowlist em modo hibrido.
        if (! empty($policy->blockedProvinces) && $location->provinceSlug !== null && in_array($location->provinceSlug, $policy->blockedProvinces, true)) {
            return $this->deny(DecisionReasonCode::ACCESS_DENIED_PROVINCE, 'Acesso negado: provincia bloqueada pela politica hibrida.', $location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        if (! empty($policy->allowedProvinces)) {
            return $this->evaluateProvinceAllowlist($location, $policy, $risk, $evidence, $warnings, $startedAt);
        }

        return $this->evaluateCountry($location, $policy, $risk, $evidence, $warnings, $startedAt);
    }

    /**
     * Avaliacao de risco baseada nos sinais disponiveis na localizacao.
     * Ver secao 13. Nao pretende ser exaustiva — a Fase 5 (Seguranca)
     * expande esta logica com deteccao de incompatibilidade IP/GPS,
     * mudanca impossivel de localizacao, etc.
     */
    private function assessRisk(LocationResult $location, array &$warnings, array &$evidence): RiskLevel
    {
        $flagsCount = (int) $location->isVpn + (int) $location->isProxy + (int) $location->isTor + (int) $location->isDatacenter;

        if ($location->isTor) {
            $evidence[] = 'signal:tor';
        }
        if ($location->isVpn) {
            $evidence[] = 'signal:vpn';
        }
        if ($location->isProxy) {
            $evidence[] = 'signal:proxy';
        }
        if ($location->isDatacenter) {
            $evidence[] = 'signal:datacenter';
        }

        if (! $location->confidence->meetsMinimum(ConfidenceLevel::MEDIUM)) {
            $warnings[] = 'Confianca da localizacao abaixo do nivel medio.';
        }

        return match (true) {
            $flagsCount >= 2 => RiskLevel::CRITICAL,
            $location->isTor => RiskLevel::HIGH,
            $flagsCount === 1 => RiskLevel::MEDIUM,
            default => RiskLevel::LOW,
        };
    }

    private function allow(DecisionReasonCode $code, string $reason, LocationResult $location, GeoAccessPolicyConfig $policy, RiskLevel $risk, array $evidence, array $warnings, float $startedAt): GeoAccessDecision
    {
        return new GeoAccessDecision(
            allowed: true,
            reasonCode: $code,
            reason: $reason,
            location: $location,
            policyIdentifier: $policy->identifier,
            confidence: $location->confidence,
            risk: $risk,
            evidence: $evidence,
            warnings: $warnings,
            appliedExceptions: [],
            decidedAt: new DateTimeImmutable(),
            processingTimeMs: (microtime(true) - $startedAt) * 1000,
        );
    }

    private function deny(DecisionReasonCode $code, string $reason, LocationResult $location, GeoAccessPolicyConfig $policy, RiskLevel $risk, array $evidence, array $warnings, float $startedAt): GeoAccessDecision
    {
        return new GeoAccessDecision(
            allowed: false,
            reasonCode: $code,
            reason: $reason,
            location: $location,
            policyIdentifier: $policy->identifier,
            confidence: $location->confidence,
            risk: $risk,
            evidence: $evidence,
            warnings: $warnings,
            appliedExceptions: [],
            decidedAt: new DateTimeImmutable(),
            processingTimeMs: (microtime(true) - $startedAt) * 1000,
        );
    }
}
