<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Unit;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorObservation;
use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorProfileData;
use JoseQuembi\AngolaGeoGuard\Enums\ThreatLevel;
use JoseQuembi\AngolaGeoGuard\Security\Threat\ThreatScorer;
use JoseQuembi\AngolaGeoGuard\Security\Threat\ThreatScorerConfig;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

final class ThreatScorerTest extends TestCase
{
    private ThreatScorer $scorer;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->scorer = new ThreatScorer(windowMinutes: 15);
        $this->now = new DateTimeImmutable('2026-07-28T10:00:00Z');
    }

    private function observation(string $key, DateTimeImmutable $at, array $overrides = []): BehaviorObservation
    {
        return new BehaviorObservation(
            subjectKey: $key,
            observedAt: $at,
            coordinates: $overrides['coordinates'] ?? null,
            countryCode: $overrides['countryCode'] ?? 'AO',
            provinceSlug: $overrides['provinceSlug'] ?? 'huila',
            isVpn: $overrides['isVpn'] ?? false,
            isProxy: $overrides['isProxy'] ?? false,
            isTor: $overrides['isTor'] ?? false,
            allowed: $overrides['allowed'] ?? true,
        );
    }

    public function test_first_ever_request_has_no_signals(): void
    {
        $profile = BehaviorProfileData::fresh('subject-1', $this->now);
        $observation = $this->observation('subject-1', $this->now, ['coordinates' => new Coordinates(-14.9172, 13.4925)]);

        $assessment = $this->scorer->score($profile, $observation);

        $this->assertSame(0, $assessment->score);
        $this->assertSame(ThreatLevel::NONE, $assessment->level);
    }

    public function test_detects_real_impossible_travel(): void
    {
        $profile = BehaviorProfileData::fresh('spoofer-1', $this->now);
        $first = $this->observation('spoofer-1', $this->now, ['coordinates' => new Coordinates(-8.8390, 13.2894)]); // Luanda
        $profile = $this->scorer->nextProfile($profile, $first, false);

        // Nova Iorque, 10 segundos depois — fisicamente impossivel.
        $second = $this->observation('spoofer-1', $this->now->modify('+10 seconds'), ['coordinates' => new Coordinates(40.7128, -74.0060)]);

        $assessment = $this->scorer->score($profile, $second);

        $this->assertContains('impossible_travel', $assessment->signals);
        $this->assertGreaterThanOrEqual(40, $assessment->score);
    }

    public function test_legitimate_commercial_flight_does_not_trigger_impossible_travel(): void
    {
        // Regressao de falso-positivo: Luanda -> Lisboa (~5000km) em
        // 6 horas de voo comercial (~830km/h medio) nao deve disparar
        // o sinal, ja que esta abaixo do limiar por defeito (1000km/h).
        $profile = BehaviorProfileData::fresh('traveler-1', $this->now);
        $departure = $this->observation('traveler-1', $this->now, ['coordinates' => new Coordinates(-8.8390, 13.2894)]);
        $profile = $this->scorer->nextProfile($profile, $departure, false);

        $arrival = $this->observation('traveler-1', $this->now->modify('+6 hours'), [
            'coordinates' => new Coordinates(38.7223, -9.1393),
            'countryCode' => 'PT',
        ]);

        $assessment = $this->scorer->score($profile, $arrival);

        $this->assertNotContains('impossible_travel', $assessment->signals);
    }

    public function test_gps_jitter_over_short_distance_does_not_trigger_impossible_travel(): void
    {
        // Regressao de falso-positivo: 2.4km em 3 segundos e ruido de
        // GPS/IP tipico, nao movimento real — a distancia minima
        // (50km por defeito) impede o falso alarme.
        $profile = BehaviorProfileData::fresh('jitter-1', $this->now);
        $first = $this->observation('jitter-1', $this->now, ['coordinates' => new Coordinates(-8.8390, 13.2894)]);
        $profile = $this->scorer->nextProfile($profile, $first, false);

        $second = $this->observation('jitter-1', $this->now->modify('+3 seconds'), ['coordinates' => new Coordinates(-8.8570, 13.3050)]);

        $assessment = $this->scorer->score($profile, $second);

        $this->assertNotContains('impossible_travel', $assessment->signals);
    }

    public function test_detects_high_denial_ratio_with_sufficient_sample_size(): void
    {
        $profile = BehaviorProfileData::fresh('subject-3', $this->now);

        for ($i = 0; $i < 7; $i++) {
            $observation = $this->observation('subject-3', $this->now->modify("+{$i} minutes"), ['allowed' => false]);
            $profile = $this->scorer->nextProfile($profile, $observation, false);
        }

        $final = $this->observation('subject-3', $this->now->modify('+8 minutes'), ['allowed' => false]);
        $assessment = $this->scorer->score($profile, $final);

        $this->assertContains('high_denial_ratio', $assessment->signals);
    }

    public function test_denial_ratio_does_not_trigger_below_minimum_sample_size(): void
    {
        // Regressao de falso-positivo: o limiar minimo de amostras
        // subiu de 5 para 8, para evitar racios estatisticamente ruidosos.
        $profile = BehaviorProfileData::fresh('prober-1', $this->now);

        for ($i = 0; $i < 6; $i++) {
            $observation = $this->observation('prober-1', $this->now->modify("+{$i} minutes"), ['allowed' => false]);
            $profile = $this->scorer->nextProfile($profile, $observation, false);
        }

        $seventh = $this->observation('prober-1', $this->now->modify('+7 minutes'), ['allowed' => false]);
        $assessment = $this->scorer->score($profile, $seventh);

        $this->assertNotContains('high_denial_ratio', $assessment->signals);
    }

    public function test_detects_province_enumeration(): void
    {
        $profile = BehaviorProfileData::fresh('subject-4', $this->now);

        foreach (['huila', 'benguela', 'namibe', 'luanda'] as $i => $province) {
            $observation = $this->observation('subject-4', $this->now->modify("+{$i} minutes"), ['provinceSlug' => $province]);
            $profile = $this->scorer->nextProfile($profile, $observation, false);
        }

        $final = $this->observation('subject-4', $this->now->modify('+10 minutes'), ['provinceSlug' => 'cabinda']);
        $assessment = $this->scorer->score($profile, $final);

        $this->assertContains('province_enumeration', $assessment->signals);
    }

    public function test_learns_baseline_and_detects_rapid_fire_once_baseline_is_trustworthy(): void
    {
        $profile = BehaviorProfileData::fresh('subject-5', $this->now);

        for ($i = 1; $i <= 5; $i++) {
            $observation = $this->observation('subject-5', $this->now->modify("+{$i} minutes"));
            $profile = $this->scorer->nextProfile($profile, $observation, false);
        }

        $this->assertGreaterThan(50, $profile->ewmaIntervalSeconds);
        $this->assertLessThan(70, $profile->ewmaIntervalSeconds);

        $rapid = $this->observation('subject-5', $this->now->modify('+5 minutes +5 seconds'));
        $assessment = $this->scorer->score($profile, $rapid);

        $this->assertContains('rapid_fire', $assessment->signals);
    }

    public function test_rapid_fire_does_not_trigger_with_untrustworthy_baseline(): void
    {
        // Regressao de falso-positivo: com apenas 2 amostras a linha de
        // base EWMA e demasiado instavel; sao necessarias >= 3.
        $profile = BehaviorProfileData::fresh('newuser-1', $this->now);

        $first = $this->observation('newuser-1', $this->now);
        $profile = $this->scorer->nextProfile($profile, $first, false);

        $second = $this->observation('newuser-1', $this->now->modify('+60 seconds'));
        $profile = $this->scorer->nextProfile($profile, $second, false);

        $rapidThird = $this->observation('newuser-1', $this->now->modify('+61 seconds'));
        $assessment = $this->scorer->score($profile, $rapidThird);

        $this->assertNotContains('rapid_fire', $assessment->signals);
    }

    public function test_single_evasion_signal_toggle_does_not_trigger_cycling(): void
    {
        // Regressao de falso-positivo: uma unica reconexao de VPN e
        // comportamento normal do cliente, nao deve ser tratada como
        // padrao de evasao.
        $profile = BehaviorProfileData::fresh('subject-6', $this->now);
        $first = $this->observation('subject-6', $this->now, ['isVpn' => true]);
        $profile = $this->scorer->nextProfile($profile, $first, false);

        $second = $this->observation('subject-6', $this->now->modify('+1 minute'), ['isVpn' => false]);
        $assessment = $this->scorer->score($profile, $second);

        $this->assertNotContains('evasion_signal_cycling', $assessment->signals);
    }

    public function test_repeated_evasion_signal_toggling_triggers_cycling(): void
    {
        $profile = BehaviorProfileData::fresh('subject-6b', $this->now);
        $first = $this->observation('subject-6b', $this->now, ['isVpn' => true]);
        $profile = $this->scorer->nextProfile($profile, $first, false);

        $second = $this->observation('subject-6b', $this->now->modify('+1 minute'), ['isVpn' => false]);
        $profile = $this->scorer->nextProfile($profile, $second, false);

        $third = $this->observation('subject-6b', $this->now->modify('+2 minutes'), ['isVpn' => true]);
        $assessment = $this->scorer->score($profile, $third);

        $this->assertContains('evasion_signal_cycling', $assessment->signals);
    }

    public function test_thresholds_are_configurable(): void
    {
        $strictConfig = new ThreatScorerConfig(impossibleTravelKmh: 200.0, impossibleTravelMinDistanceKm: 10.0);
        $strictScorer = new ThreatScorer(config: $strictConfig, windowMinutes: 15);

        $profile = BehaviorProfileData::fresh('custom-1', $this->now);
        $first = $this->observation('custom-1', $this->now, ['coordinates' => new Coordinates(-8.8390, 13.2894)]);
        $profile = $strictScorer->nextProfile($profile, $first, false);

        // ~270km em 54 minutos = 300km/h: excede o limiar customizado
        // (200km/h) mas ficaria abaixo do limiar por defeito (1000km/h).
        $second = $this->observation('custom-1', $this->now->modify('+54 minutes'), ['coordinates' => new Coordinates(-11.2833, 13.2894)]);
        $assessment = $strictScorer->score($profile, $second);

        $this->assertContains('impossible_travel', $assessment->signals);
    }
}
