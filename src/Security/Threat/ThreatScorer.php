<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Security\Threat;

use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorObservation;
use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorProfileData;
use JoseQuembi\AngolaGeoGuard\DTOs\ThreatAssessment;
use JoseQuembi\AngolaGeoGuard\Enums\CountermeasureAction;
use JoseQuembi\AngolaGeoGuard\Enums\ThreatLevel;

/**
 * Motor de deteccao de comportamento anomalo/suspeito.
 *
 * IMPORTANTE — sobre o que este componente E e NAO E: isto e um
 * classificador HEURISTICO baseado em estatistica simples (linha de
 * base por media movel exponencial, contadores de janela deslizante,
 * regras de limiar). Nao e um modelo de machine learning treinado,
 * nao faz previsao probabilistica calibrada, e nao deve ser descrito
 * como tal em documentacao ou UI. "Aprender" aqui significa
 * especificamente: (1) manter uma linha de base adaptativa do
 * intervalo normal entre pedidos de cada sujeito via EWMA, e (2)
 * reter memoria de violacoes passadas (`violationCount`) que
 * escalona a severidade da resposta em ocorrencias futuras — nao
 * mais do que isso. Esta honestidade sobre o mecanismo e deliberada:
 * sobrevender deteccao heuristica como "IA que preve invasoes" seria
 * enganoso e daria uma falsa sensacao de seguranca.
 *
 * Todos os limiares e pesos sao configuraveis via ThreatScorerConfig
 * (ver esse ficheiro para o raciocinio de calibracao de cada valor
 * por defeito e os ajustes feitos para reduzir falsos positivos).
 *
 * Sinais avaliados por pedido (ver secao 13/46 do prompt mestre):
 *   - Viagem impossivel: velocidade implicita entre duas localizacoes
 *     consecutivas do mesmo sujeito excede o razoavel, e a distancia
 *     e grande o suficiente para descartar ruido de GPS/IP.
 *   - Racio de negacao elevado: multiplas tentativas negadas numa
 *     janela curta sugerem sondagem/forca bruta de politicas.
 *   - Rajada de pedidos: intervalo entre pedidos muito abaixo da
 *     linha de base aprendida (EWMA) para o sujeito, so avaliado
 *     depois de a linha de base ter amostras suficientes para ser fiavel.
 *   - Enumeracao de provincias/paises: numero elevado de provincias
 *     ou paises distintos tentados numa janela curta.
 *   - Ciclagem de sinais de evasao: alternancia REPETIDA (nao uma
 *     unica ocorrencia) de VPN/Proxy/Tor entre pedidos do sujeito.
 */
final class ThreatScorer
{
    public function __construct(
        private readonly ThreatScorerConfig $config = new ThreatScorerConfig(),
        private readonly int $windowMinutes = 15,
        private readonly int $maxTrackedProvinces = 30,
        private readonly int $maxTrackedCountries = 10,
    ) {
    }

    /**
     * Calcula a nova pontuacao de ameaca com base no perfil anterior
     * e na observacao atual. Funcao pura — nao tem efeitos laterais
     * nem acede a armazenamento.
     */
    public function score(BehaviorProfileData $profile, BehaviorObservation $observation): ThreatAssessment
    {
        $signals = [];
        $score = 0;
        $c = $this->config;

        // --- Sinal 1: viagem impossivel ---
        // So dispara se a distancia for grande o suficiente para
        // descartar ruido de GPS/resolucao de IP (ver ThreatScorerConfig).
        if ($profile->lastCoordinates !== null && $observation->coordinates !== null && $profile->lastObservedAt !== null) {
            $elapsedSeconds = $observation->observedAt->getTimestamp() - $profile->lastObservedAt->getTimestamp();

            if ($elapsedSeconds > 0) {
                $distanceKm = $profile->lastCoordinates->distanceTo($observation->coordinates) / 1000;

                if ($distanceKm >= $c->impossibleTravelMinDistanceKm) {
                    $impliedSpeedKmh = $distanceKm / ($elapsedSeconds / 3600);

                    if ($impliedSpeedKmh > $c->impossibleTravelKmh) {
                        $signals[] = 'impossible_travel';
                        $score += $c->impossibleTravelWeight;
                    }
                }
            }
        }

        // --- Sinal 2: racio de negacao elevado ---
        $projectedRequestCount = $profile->windowRequestCount + 1;
        $projectedDeniedCount = $profile->windowDeniedCount + ($observation->allowed ? 0 : 1);

        if ($projectedRequestCount >= $c->minRequestsForDenialRatio) {
            $denialRatio = $projectedDeniedCount / $projectedRequestCount;

            if ($denialRatio >= $c->denialRatioThreshold) {
                $signals[] = 'high_denial_ratio';
                $score += $c->denialRatioWeight;
            }
        }

        // --- Sinal 3: rajada de pedidos (abaixo da linha de base aprendida) ---
        // So avaliado quando ja existem amostras suficientes para a
        // media EWMA ser estatisticamente significativa.
        if ($profile->ewmaIntervalSeconds !== null
            && $profile->lastObservedAt !== null
            && $profile->windowRequestCount >= $c->rapidFireMinBaselineSamples
        ) {
            $actualInterval = $observation->observedAt->getTimestamp() - $profile->lastObservedAt->getTimestamp();

            if ($actualInterval >= 0 && $actualInterval < $profile->ewmaIntervalSeconds * $c->rapidFireRatio) {
                $signals[] = 'rapid_fire';
                $score += $c->rapidFireWeight;
            }
        }

        // --- Sinal 4: enumeracao de provincias ---
        $projectedProvinces = $observation->provinceSlug !== null
            ? array_unique([...$profile->distinctProvincesInWindow, $observation->provinceSlug])
            : $profile->distinctProvincesInWindow;

        if (count($projectedProvinces) >= $c->provinceEnumerationThreshold) {
            $signals[] = 'province_enumeration';
            $score += $c->provinceEnumerationWeight;
        }

        // --- Sinal 5: mudanca frequente de pais ("country hopping") ---
        $projectedCountries = $observation->countryCode !== null
            ? array_unique([...$profile->distinctCountriesInWindow, $observation->countryCode])
            : $profile->distinctCountriesInWindow;

        if (count($projectedCountries) >= $c->countryHoppingThreshold) {
            $signals[] = 'country_hopping';
            $score += $c->countryHoppingWeight;
        }

        // --- Sinal 6: ciclagem REPETIDA de sinais de evasao ---
        // Uma unica alternancia de VPN/Proxy/Tor (ex.: reconexao normal
        // do cliente VPN) nao e suficiente; exige-se que o padrao se
        // repita `evasionCyclingMinOccurrences` vezes dentro da janela.
        $projectedFlagChangeCount = $this->projectedFlagChangeCount($profile, $observation);

        if ($projectedFlagChangeCount >= $c->evasionCyclingMinOccurrences) {
            $signals[] = 'evasion_signal_cycling';
            $score += $c->evasionCyclingWeight;
        }

        $score = min(100, $score);
        $level = $c->levelForScore($score);

        return new ThreatAssessment(
            score: $score,
            level: $level,
            signals: $signals,
            recommendedAction: $this->actionFor($level),
        );
    }

    /**
     * Calcula o proximo estado do perfil (imutavel) apos incorporar
     * a observacao atual. A janela deslizante reinicia a cada
     * `windowMinutes`, mas `violationCount` e `ewmaIntervalSeconds`
     * SAO PRESERVADOS entre janelas — e essa persistencia de longo
     * prazo que constitui a "aprendizagem" do sistema.
     */
    public function nextProfile(BehaviorProfileData $profile, BehaviorObservation $observation, bool $violationOccurred): BehaviorProfileData
    {
        $windowExpired = $observation->observedAt->getTimestamp() - $profile->windowStartedAt->getTimestamp() > $this->windowMinutes * 60;

        $windowStartedAt = $windowExpired ? $observation->observedAt : $profile->windowStartedAt;
        $windowRequestCount = ($windowExpired ? 0 : $profile->windowRequestCount) + 1;
        $windowDeniedCount = ($windowExpired ? 0 : $profile->windowDeniedCount) + ($observation->allowed ? 0 : 1);

        $baseProvinces = $windowExpired ? [] : $profile->distinctProvincesInWindow;
        $distinctProvinces = $observation->provinceSlug !== null
            ? array_slice(array_unique([...$baseProvinces, $observation->provinceSlug]), -$this->maxTrackedProvinces)
            : $baseProvinces;

        $baseCountries = $windowExpired ? [] : $profile->distinctCountriesInWindow;
        $distinctCountries = $observation->countryCode !== null
            ? array_slice(array_unique([...$baseCountries, $observation->countryCode]), -$this->maxTrackedCountries)
            : $baseCountries;

        $ewmaIntervalSeconds = $profile->ewmaIntervalSeconds;

        if ($profile->lastObservedAt !== null) {
            $actualInterval = max(0, $observation->observedAt->getTimestamp() - $profile->lastObservedAt->getTimestamp());

            $ewmaIntervalSeconds = $ewmaIntervalSeconds === null
                ? (float) $actualInterval
                : ($this->config->ewmaAlpha * $actualInterval) + ((1 - $this->config->ewmaAlpha) * $ewmaIntervalSeconds);
        }

        $flagChangeCountInWindow = $windowExpired ? 0 : $this->projectedFlagChangeCount($profile, $observation);

        return new BehaviorProfileData(
            subjectKey: $profile->subjectKey,
            windowStartedAt: $windowStartedAt,
            windowRequestCount: $windowRequestCount,
            windowDeniedCount: $windowDeniedCount,
            distinctProvincesInWindow: array_values($distinctProvinces),
            distinctCountriesInWindow: array_values($distinctCountries),
            lastObservedAt: $observation->observedAt,
            lastCoordinates: $observation->coordinates ?? $profile->lastCoordinates,
            lastIsVpn: $observation->isVpn,
            lastIsProxy: $observation->isProxy,
            lastIsTor: $observation->isTor,
            flagChangeCountInWindow: $flagChangeCountInWindow,
            ewmaIntervalSeconds: $ewmaIntervalSeconds,
            violationCount: $profile->violationCount + ($violationOccurred ? 1 : 0),
            quarantinedUntil: $profile->quarantinedUntil,
            lastQuarantineDurationSeconds: $profile->lastQuarantineDurationSeconds,
        );
    }

    private function projectedFlagChangeCount(BehaviorProfileData $profile, BehaviorObservation $observation): int
    {
        if ($profile->lastObservedAt === null) {
            return 0;
        }

        $flagsChanged = $profile->lastIsVpn !== $observation->isVpn
            || $profile->lastIsProxy !== $observation->isProxy
            || $profile->lastIsTor !== $observation->isTor;

        $anyFlagActive = $observation->isVpn || $observation->isProxy || $observation->isTor
            || $profile->lastIsVpn || $profile->lastIsProxy || $profile->lastIsTor;

        return $profile->flagChangeCountInWindow + ($flagsChanged && $anyFlagActive ? 1 : 0);
    }

    private function actionFor(ThreatLevel $level): CountermeasureAction
    {
        return match ($level) {
            ThreatLevel::NONE => CountermeasureAction::NONE,
            ThreatLevel::WATCHING => CountermeasureAction::LOG_ONLY,
            ThreatLevel::SUSPICIOUS => CountermeasureAction::CHALLENGE,
            ThreatLevel::CONFIRMED_THREAT => CountermeasureAction::QUARANTINE,
        };
    }
}
