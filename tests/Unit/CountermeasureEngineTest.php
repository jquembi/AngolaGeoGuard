<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Unit;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorProfileData;
use JoseQuembi\AngolaGeoGuard\DTOs\ThreatAssessment;
use JoseQuembi\AngolaGeoGuard\Enums\CountermeasureAction;
use JoseQuembi\AngolaGeoGuard\Enums\ThreatLevel;
use JoseQuembi\AngolaGeoGuard\Security\Threat\CountermeasureEngine;
use PHPUnit\Framework\TestCase;

final class CountermeasureEngineTest extends TestCase
{
    private CountermeasureEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new CountermeasureEngine(baseQuarantineSeconds: 900, maxQuarantineSeconds: 86400, escalationFactor: 2.0);
    }

    public function test_quarantine_duration_escalates_with_prior_violations(): void
    {
        $this->assertSame(900, $this->engine->calculateEscalatedDuration(0));
        $this->assertSame(1800, $this->engine->calculateEscalatedDuration(1));
        $this->assertSame(3600, $this->engine->calculateEscalatedDuration(2));
        $this->assertSame(7200, $this->engine->calculateEscalatedDuration(3));
    }

    public function test_quarantine_duration_caps_at_maximum(): void
    {
        $this->assertSame(86400, $this->engine->calculateEscalatedDuration(20));
    }

    public function test_apply_sets_quarantine_on_repeat_offender(): void
    {
        $now = new DateTimeImmutable('2026-07-28T10:00:00Z');

        $assessment = new ThreatAssessment(
            score: 85,
            level: ThreatLevel::CONFIRMED_THREAT,
            signals: ['impossible_travel', 'country_hopping'],
            recommendedAction: CountermeasureAction::QUARANTINE,
        );

        $profile = new BehaviorProfileData(
            subjectKey: 'subject-8',
            windowStartedAt: $now,
            windowRequestCount: 1,
            windowDeniedCount: 1,
            distinctProvincesInWindow: [],
            distinctCountriesInWindow: [],
            lastObservedAt: $now,
            lastCoordinates: null,
            lastIsVpn: false,
            lastIsProxy: false,
            lastIsTor: false,
            flagChangeCountInWindow: 0,
            ewmaIntervalSeconds: null,
            violationCount: 2,
            quarantinedUntil: null,
            lastQuarantineDurationSeconds: null,
        );

        $result = $this->engine->apply($assessment, $profile, $now);

        $this->assertSame(3600, $result['assessment']->quarantineDurationSeconds);
        $this->assertNotNull($result['profile']->quarantinedUntil);
        $this->assertTrue($result['profile']->isCurrentlyQuarantined($now));
        $this->assertFalse($result['profile']->isCurrentlyQuarantined($now->modify('+2 hours')));
    }

    public function test_apply_is_noop_when_action_is_not_quarantine(): void
    {
        $now = new DateTimeImmutable();

        $assessment = new ThreatAssessment(
            score: 30,
            level: ThreatLevel::WATCHING,
            signals: ['rapid_fire'],
            recommendedAction: CountermeasureAction::LOG_ONLY,
        );

        $profile = BehaviorProfileData::fresh('subject-9', $now);

        $result = $this->engine->apply($assessment, $profile, $now);

        $this->assertNull($result['assessment']->quarantineDurationSeconds);
        $this->assertNull($result['profile']->quarantinedUntil);
    }
}
