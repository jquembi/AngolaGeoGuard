<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Models;

use Illuminate\Database\Eloquent\Model;
use JoseQuembi\AngolaGeoGuard\DTOs\BehaviorProfileData;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Persistencia Eloquent do estado comportamental aprendido de um
 * sujeito. A logica de deteccao em si (ThreatScorer) nunca depende
 * deste model — apenas o repositorio de persistencia o usa,
 * mantendo o algoritmo testavel sem base de dados.
 *
 * @property string                          $subject_key
 * @property \Illuminate\Support\Carbon      $window_started_at
 * @property int                             $window_request_count
 * @property int                             $window_denied_count
 * @property array<string>                   $distinct_provinces_in_window
 * @property array<string>                   $distinct_countries_in_window
 * @property \Illuminate\Support\Carbon|null $last_observed_at
 * @property float|null                      $last_latitude
 * @property float|null                      $last_longitude
 * @property bool                            $last_is_vpn
 * @property bool                            $last_is_proxy
 * @property bool                            $last_is_tor
 * @property int                             $flag_change_count_in_window
 * @property float|null                      $ewma_interval_seconds
 * @property \Illuminate\Support\Carbon|null $quarantined_until
 * @property int                             $violation_count
 * @property int|null                        $last_quarantine_duration_seconds
 */
final class GeoBehaviorProfile extends Model
{
    protected $table = 'geo_behavior_profiles';

    protected $fillable = [
        'subject_key', 'tenant_id', 'window_started_at', 'window_request_count',
        'window_denied_count', 'distinct_provinces_in_window', 'distinct_countries_in_window',
        'last_observed_at', 'last_latitude', 'last_longitude', 'last_is_vpn',
        'last_is_proxy', 'last_is_tor', 'flag_change_count_in_window', 'ewma_interval_seconds', 'violation_count',
        'quarantined_until', 'last_quarantine_duration_seconds',
    ];

    protected $casts = [
        'window_started_at' => 'datetime',
        'distinct_provinces_in_window' => 'array',
        'distinct_countries_in_window' => 'array',
        'last_observed_at' => 'datetime',
        'last_latitude' => 'float',
        'last_longitude' => 'float',
        'last_is_vpn' => 'boolean',
        'last_is_proxy' => 'boolean',
        'last_is_tor' => 'boolean',
        'flag_change_count_in_window' => 'integer',
        'ewma_interval_seconds' => 'float',
        'violation_count' => 'integer',
        'quarantined_until' => 'datetime',
    ];

    public function toBehaviorProfileData(): BehaviorProfileData
    {
        return new BehaviorProfileData(
            subjectKey: $this->subject_key,
            windowStartedAt: \DateTimeImmutable::createFromInterface($this->window_started_at),
            windowRequestCount: $this->window_request_count,
            windowDeniedCount: $this->window_denied_count,
            distinctProvincesInWindow: $this->distinct_provinces_in_window ?? [],
            distinctCountriesInWindow: $this->distinct_countries_in_window ?? [],
            lastObservedAt: $this->last_observed_at ? \DateTimeImmutable::createFromInterface($this->last_observed_at) : null,
            lastCoordinates: ($this->last_latitude !== null && $this->last_longitude !== null)
                ? new Coordinates($this->last_latitude, $this->last_longitude)
                : null,
            lastIsVpn: $this->last_is_vpn,
            lastIsProxy: $this->last_is_proxy,
            lastIsTor: $this->last_is_tor,
            flagChangeCountInWindow: $this->flag_change_count_in_window,
            ewmaIntervalSeconds: $this->ewma_interval_seconds,
            violationCount: $this->violation_count,
            quarantinedUntil: $this->quarantined_until ? \DateTimeImmutable::createFromInterface($this->quarantined_until) : null,
            lastQuarantineDurationSeconds: $this->last_quarantine_duration_seconds,
        );
    }

    public function fillFromBehaviorProfileData(BehaviorProfileData $data, ?string $tenantId = null): void
    {
        $this->fill([
            'subject_key' => $data->subjectKey,
            'tenant_id' => $tenantId,
            'window_started_at' => $data->windowStartedAt,
            'window_request_count' => $data->windowRequestCount,
            'window_denied_count' => $data->windowDeniedCount,
            'distinct_provinces_in_window' => $data->distinctProvincesInWindow,
            'distinct_countries_in_window' => $data->distinctCountriesInWindow,
            'last_observed_at' => $data->lastObservedAt,
            'last_latitude' => $data->lastCoordinates?->latitude,
            'last_longitude' => $data->lastCoordinates?->longitude,
            'last_is_vpn' => $data->lastIsVpn,
            'last_is_proxy' => $data->lastIsProxy,
            'last_is_tor' => $data->lastIsTor,
            'flag_change_count_in_window' => $data->flagChangeCountInWindow,
            'ewma_interval_seconds' => $data->ewmaIntervalSeconds,
            'violation_count' => $data->violationCount,
            'quarantined_until' => $data->quarantinedUntil,
            'last_quarantine_duration_seconds' => $data->lastQuarantineDurationSeconds,
        ]);
    }
}
