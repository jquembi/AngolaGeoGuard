<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Security\Threat;

use JoseQuembi\AngolaGeoGuard\Enums\ThreatLevel;

/**
 * Todos os limiares e pesos usados por ThreatScorer, extraidos para
 * um DTO configuravel em vez de constantes fixas — permite calibrar
 * o algoritmo ao perfil de risco de cada aplicacao sem editar codigo
 * do pacote. Ver `angola-geoguard.threat_detection.thresholds`.
 *
 * Os valores por defeito foram revistos para reduzir falsos positivos
 * comuns num sistema de producao real:
 *
 *   - `impossibleTravelKmh` subiu de 900 para 1000: a velocidade de
 *     cruzeiro de um voo comercial ronda 850-900 km/h, pelo que 900
 *     classificaria viajantes legitimos como suspeitos.
 *   - `impossibleTravelMinDistanceKm` (novo, 50km): distancias
 *     pequenas com intervalos muito curtos entre pedidos produzem
 *     velocidades implicitas artificialmente altas so por ruido de
 *     GPS/resolucao de IP (ex.: 2km em 3s = 2400km/h sem movimento
 *     real algum). Abaixo deste limiar, o sinal nunca dispara.
 *   - `rapidFireMinBaselineSamples` (novo, 3): a linha de base EWMA
 *     so e considerada fiavel apos pelo menos N pedidos anteriores —
 *     com 1-2 amostras a media e demasiado instavel para servir de
 *     comparacao.
 *   - `evasionCyclingMinOccurrences` (novo, 2): uma unica alternancia
 *     de VPN/Proxy/Tor (ex.: reconexao normal do cliente VPN) ja nao
 *     e suficiente; exige-se repeticao do padrao dentro da janela.
 *   - `minRequestsForDenialRatio` subiu de 5 para 8: com amostras
 *     pequenas o racio de negacao e estatisticamente ruidoso.
 */
final class ThreatScorerConfig
{
    public function __construct(
        public readonly float $impossibleTravelKmh = 1000.0,
        public readonly float $impossibleTravelMinDistanceKm = 50.0,
        public readonly int $impossibleTravelWeight = 40,
        public readonly float $rapidFireRatio = 0.2,
        public readonly int $rapidFireMinBaselineSamples = 3,
        public readonly int $rapidFireWeight = 15,
        public readonly int $minRequestsForDenialRatio = 8,
        public readonly float $denialRatioThreshold = 0.6,
        public readonly int $denialRatioWeight = 25,
        public readonly int $provinceEnumerationThreshold = 5,
        public readonly int $provinceEnumerationWeight = 20,
        public readonly int $countryHoppingThreshold = 3,
        public readonly int $countryHoppingWeight = 25,
        public readonly int $evasionCyclingMinOccurrences = 2,
        public readonly int $evasionCyclingWeight = 15,
        public readonly float $ewmaAlpha = 0.3,
        public readonly int $watchingScoreThreshold = 20,
        public readonly int $suspiciousScoreThreshold = 45,
        public readonly int $confirmedThreatScoreThreshold = 70,
    ) {
    }

    public static function default(): self
    {
        return new self();
    }

    public static function fromArray(array $data): self
    {
        $defaults = self::default();

        return new self(
            impossibleTravelKmh: (float) ($data['impossible_travel_kmh'] ?? $defaults->impossibleTravelKmh),
            impossibleTravelMinDistanceKm: (float) ($data['impossible_travel_min_distance_km'] ?? $defaults->impossibleTravelMinDistanceKm),
            impossibleTravelWeight: (int) ($data['impossible_travel_weight'] ?? $defaults->impossibleTravelWeight),
            rapidFireRatio: (float) ($data['rapid_fire_ratio'] ?? $defaults->rapidFireRatio),
            rapidFireMinBaselineSamples: (int) ($data['rapid_fire_min_baseline_samples'] ?? $defaults->rapidFireMinBaselineSamples),
            rapidFireWeight: (int) ($data['rapid_fire_weight'] ?? $defaults->rapidFireWeight),
            minRequestsForDenialRatio: (int) ($data['min_requests_for_denial_ratio'] ?? $defaults->minRequestsForDenialRatio),
            denialRatioThreshold: (float) ($data['denial_ratio_threshold'] ?? $defaults->denialRatioThreshold),
            denialRatioWeight: (int) ($data['denial_ratio_weight'] ?? $defaults->denialRatioWeight),
            provinceEnumerationThreshold: (int) ($data['province_enumeration_threshold'] ?? $defaults->provinceEnumerationThreshold),
            provinceEnumerationWeight: (int) ($data['province_enumeration_weight'] ?? $defaults->provinceEnumerationWeight),
            countryHoppingThreshold: (int) ($data['country_hopping_threshold'] ?? $defaults->countryHoppingThreshold),
            countryHoppingWeight: (int) ($data['country_hopping_weight'] ?? $defaults->countryHoppingWeight),
            evasionCyclingMinOccurrences: (int) ($data['evasion_cycling_min_occurrences'] ?? $defaults->evasionCyclingMinOccurrences),
            evasionCyclingWeight: (int) ($data['evasion_cycling_weight'] ?? $defaults->evasionCyclingWeight),
            ewmaAlpha: (float) ($data['ewma_alpha'] ?? $defaults->ewmaAlpha),
            watchingScoreThreshold: (int) ($data['watching_score_threshold'] ?? $defaults->watchingScoreThreshold),
            suspiciousScoreThreshold: (int) ($data['suspicious_score_threshold'] ?? $defaults->suspiciousScoreThreshold),
            confirmedThreatScoreThreshold: (int) ($data['confirmed_threat_score_threshold'] ?? $defaults->confirmedThreatScoreThreshold),
        );
    }

    public function levelForScore(int $score): ThreatLevel
    {
        return match (true) {
            $score >= $this->confirmedThreatScoreThreshold => ThreatLevel::CONFIRMED_THREAT,
            $score >= $this->suspiciousScoreThreshold => ThreatLevel::SUSPICIOUS,
            $score >= $this->watchingScoreThreshold => ThreatLevel::WATCHING,
            default => ThreatLevel::NONE,
        };
    }
}
