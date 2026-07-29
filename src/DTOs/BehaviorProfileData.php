<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\DTOs;

use DateTimeImmutable;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Estado comportamental acumulado ("aprendido") de um sujeito
 * (IP com hash/utilizador) ao longo do tempo. Combina uma janela
 * deslizante curta (contadores que reiniciam a cada
 * `window_minutes`, usada para deteccao de rajadas/enumeracao) com
 * memoria de longo prazo que NUNCA reinicia (`violationCount`,
 * `ewmaIntervalSeconds`) — e esta memoria de longo prazo que faz o
 * sistema "aprender": um sujeito com historico de violacoes recebe
 * contramedidas mais severas e mais rapidamente da proxima vez.
 *
 * Imutavel: todas as transicoes de estado sao feitas por
 * ThreatScorer::nextProfile(), que devolve uma nova instancia.
 */
final class BehaviorProfileData
{
    /**
     * @param array<string> $distinctProvincesInWindow
     * @param array<string> $distinctCountriesInWindow
     */
    public function __construct(
        public readonly string $subjectKey,
        public readonly DateTimeImmutable $windowStartedAt,
        public readonly int $windowRequestCount,
        public readonly int $windowDeniedCount,
        public readonly array $distinctProvincesInWindow,
        public readonly array $distinctCountriesInWindow,
        public readonly ?DateTimeImmutable $lastObservedAt,
        public readonly ?Coordinates $lastCoordinates,
        public readonly bool $lastIsVpn,
        public readonly bool $lastIsProxy,
        public readonly bool $lastIsTor,
        public readonly int $flagChangeCountInWindow,
        public readonly ?float $ewmaIntervalSeconds,
        public readonly int $violationCount,
        public readonly ?DateTimeImmutable $quarantinedUntil,
        public readonly ?int $lastQuarantineDurationSeconds,
    ) {
    }

    public static function fresh(string $subjectKey, DateTimeImmutable $now): self
    {
        return new self(
            subjectKey: $subjectKey,
            windowStartedAt: $now,
            windowRequestCount: 0,
            windowDeniedCount: 0,
            distinctProvincesInWindow: [],
            distinctCountriesInWindow: [],
            lastObservedAt: null,
            lastCoordinates: null,
            lastIsVpn: false,
            lastIsProxy: false,
            lastIsTor: false,
            flagChangeCountInWindow: 0,
            ewmaIntervalSeconds: null,
            violationCount: 0,
            quarantinedUntil: null,
            lastQuarantineDurationSeconds: null,
        );
    }

    public function isCurrentlyQuarantined(DateTimeImmutable $now): bool
    {
        return $this->quarantinedUntil !== null && $now < $this->quarantinedUntil;
    }

    public function toArray(): array
    {
        return [
            'subject_key' => $this->subjectKey,
            'window_started_at' => $this->windowStartedAt->format(DateTimeImmutable::ATOM),
            'window_request_count' => $this->windowRequestCount,
            'window_denied_count' => $this->windowDeniedCount,
            'distinct_provinces_in_window' => $this->distinctProvincesInWindow,
            'distinct_countries_in_window' => $this->distinctCountriesInWindow,
            'last_observed_at' => $this->lastObservedAt?->format(DateTimeImmutable::ATOM),
            'last_coordinates' => $this->lastCoordinates?->toArray(),
            'last_is_vpn' => $this->lastIsVpn,
            'last_is_proxy' => $this->lastIsProxy,
            'last_is_tor' => $this->lastIsTor,
            'flag_change_count_in_window' => $this->flagChangeCountInWindow,
            'ewma_interval_seconds' => $this->ewmaIntervalSeconds,
            'violation_count' => $this->violationCount,
            'quarantined_until' => $this->quarantinedUntil?->format(DateTimeImmutable::ATOM),
            'last_quarantine_duration_seconds' => $this->lastQuarantineDurationSeconds,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subjectKey: $data['subject_key'],
            windowStartedAt: new DateTimeImmutable($data['window_started_at']),
            windowRequestCount: $data['window_request_count'],
            windowDeniedCount: $data['window_denied_count'],
            distinctProvincesInWindow: $data['distinct_provinces_in_window'] ?? [],
            distinctCountriesInWindow: $data['distinct_countries_in_window'] ?? [],
            lastObservedAt: isset($data['last_observed_at']) ? new DateTimeImmutable($data['last_observed_at']) : null,
            lastCoordinates: isset($data['last_coordinates']) ? Coordinates::fromArray($data['last_coordinates']) : null,
            lastIsVpn: $data['last_is_vpn'] ?? false,
            lastIsProxy: $data['last_is_proxy'] ?? false,
            lastIsTor: $data['last_is_tor'] ?? false,
            flagChangeCountInWindow: $data['flag_change_count_in_window'] ?? 0,
            ewmaIntervalSeconds: $data['ewma_interval_seconds'] ?? null,
            violationCount: $data['violation_count'] ?? 0,
            quarantinedUntil: isset($data['quarantined_until']) ? new DateTimeImmutable($data['quarantined_until']) : null,
            lastQuarantineDurationSeconds: $data['last_quarantine_duration_seconds'] ?? null,
        );
    }
}
