<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Sugere limiares a partir do historico real registado em
 * geo_access_decisions. O comando nao altera configuracao: apenas
 * calcula valores conservadores para revisao humana.
 *
 * @phpstan-type AuditDecisionRow object{
 *     allowed: bool|int,
 *     tenant_id: string|null,
 *     user_id: string|null,
 *     ip_address: string|null,
 *     country_code: string|null,
 *     province_slug: string|null,
 *     latitude: float|int|string|null,
 *     longitude: float|int|string|null,
 *     is_vpn: bool|int,
 *     is_proxy: bool|int,
 *     is_tor: bool|int,
 *     is_datacenter: bool|int,
 *     processing_time_ms: float|int|string|null,
 *     created_at: \DateTimeInterface|string|null
 * }
 */
final class CalibrateCommand extends Command
{
    protected $signature = 'geoguard:calibrate
        {--days=30 : Janela de dias de historico a analisar}
        {--min-samples=100 : Minimo recomendado de decisoes antes de confiar nas sugestoes}
        {--limit=50000 : Numero maximo de decisoes recentes a carregar}
        {--env : Mostra tambem as variaveis .env sugeridas}';

    protected $description = 'Sugere limiares de threat detection com base no historico real de trafego.';

    public function handle(): int
    {
        if (! Schema::hasTable('geo_access_decisions')) {
            $this->components->warn('A tabela "geo_access_decisions" ainda nao existe; nao ha historico real para calibrar.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $minSamples = max(1, (int) $this->option('min-samples'));
        $limit = max(1, (int) $this->option('limit'));
        $since = now()->subDays($days);

        $rows = DB::table('geo_access_decisions')
            ->select([
                'allowed',
                'tenant_id',
                'user_id',
                'country_code',
                'province_slug',
                'latitude',
                'longitude',
                'is_vpn',
                'is_proxy',
                'is_tor',
                'is_datacenter',
                'ip_address',
                'processing_time_ms',
                'created_at',
            ])
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->components->info(sprintf('Nao ha decisoes registadas nos ultimos %d dias.', $days));

            return self::SUCCESS;
        }

        /** @var array<int, AuditDecisionRow> $decisionRows */
        $decisionRows = $rows->all();
        $stats = $this->analyse($decisionRows);
        $defaults = (array) config('angola-geoguard.threat_detection.thresholds', []);
        $recommendations = $this->recommend($stats, $defaults);

        $this->table(
            ['Metrica', 'Valor'],
            [
                ['Janela analisada', sprintf('ultimos %d dias', $days)],
                ['Decisoes analisadas', (string) $stats['total']],
                ['Sujeitos distintos', (string) $stats['subjects']],
                ['Taxa global de negacao', $this->formatRatio($stats['denial_ratio'])],
                ['Taxa de sinais VPN/Proxy/Tor/Datacenter', $this->formatRatio($stats['evasion_ratio'])],
                ['P95 intervalo entre pedidos', $this->formatSeconds($stats['p95_interval_seconds'])],
                ['P95 latencia de decisao', $stats['p95_processing_ms'] === null ? '-' : sprintf('%d ms', (int) round($stats['p95_processing_ms']))],
            ],
        );

        if ($stats['total'] < $minSamples) {
            $this->components->warn(sprintf(
                'Amostra pequena (%d/%d). Use estas sugestoes como ponto de partida, nao como calibracao definitiva.',
                $stats['total'],
                $minSamples,
            ));
        }

        $this->table(
            ['Config', 'Atual', 'Sugerido', 'Base usada'],
            array_map(
                fn (array $item): array => [
                    $item['key'],
                    $item['current'],
                    $item['suggested'],
                    $item['basis'],
                ],
                $recommendations,
            ),
        );

        if ($this->option('env')) {
            $this->newLine();
            $this->components->info('Linhas .env sugeridas:');

            foreach ($recommendations as $item) {
                $this->line(sprintf('%s=%s', $item['env'], $item['suggested']));
            }
        }

        $this->components->info('Calibracao concluida em modo somente leitura; nenhuma configuracao foi alterada.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, AuditDecisionRow> $rows
     * @return array<string, mixed>
     */
    private function analyse(array $rows): array
    {
        /** @var array<string, array{requests: int, denied: int, countries: array<string, true>, provinces: array<string, true>, flag_changes: int, last_flags: array{0: bool, 1: bool, 2: bool, 3: bool}|null, last_seen_at: int|null, last_coordinates: Coordinates|null, intervals: array<int, int>}> $subjects */
        $subjects = [];
        $total = 0;
        $denied = 0;
        $evasionCount = 0;
        $intervals = [];
        $rapidRatios = [];
        $speeds = [];
        $distances = [];
        $processingTimes = [];

        foreach ($rows as $row) {
            $total++;
            $allowed = (bool) $row->allowed;
            $denied += $allowed ? 0 : 1;

            $hasEvasionSignal = (bool) $row->is_vpn || (bool) $row->is_proxy || (bool) $row->is_tor || (bool) $row->is_datacenter;
            $evasionCount += $hasEvasionSignal ? 1 : 0;

            if ($row->processing_time_ms !== null) {
                $processingTimes[] = (float) $row->processing_time_ms;
            }

            $subjectKey = $this->subjectKey($row);
            $createdAt = $this->timestamp($row->created_at);
            $coordinates = $this->coordinates($row);

            $subjects[$subjectKey] ??= [
                'requests' => 0,
                'denied' => 0,
                'countries' => [],
                'provinces' => [],
                'flag_changes' => 0,
                'last_flags' => null,
                'last_seen_at' => null,
                'last_coordinates' => null,
                'intervals' => [],
            ];

            $subject = &$subjects[$subjectKey];
            $subject['requests']++;
            $subject['denied'] += $allowed ? 0 : 1;

            if ($row->country_code !== null) {
                $subject['countries'][(string) $row->country_code] = true;
            }

            if ($row->province_slug !== null) {
                $subject['provinces'][(string) $row->province_slug] = true;
            }

            $flags = [(bool) $row->is_vpn, (bool) $row->is_proxy, (bool) $row->is_tor, (bool) $row->is_datacenter];

            if ($subject['last_flags'] !== null && $subject['last_flags'] !== $flags && (in_array(true, $subject['last_flags'], true) || in_array(true, $flags, true))) {
                $subject['flag_changes']++;
            }

            if ($subject['last_seen_at'] !== null && $createdAt !== null) {
                $intervalSeconds = $createdAt - $subject['last_seen_at'];

                if ($intervalSeconds > 0) {
                    $intervals[] = $intervalSeconds;
                    $subject['intervals'][] = $intervalSeconds;
                }
            }

            if ($subject['last_coordinates'] instanceof Coordinates && $coordinates instanceof Coordinates && $subject['last_seen_at'] !== null && $createdAt !== null) {
                $elapsedSeconds = $createdAt - $subject['last_seen_at'];

                if ($elapsedSeconds > 0) {
                    $distanceKm = $subject['last_coordinates']->distanceTo($coordinates) / 1000;

                    if ($distanceKm > 0) {
                        $distances[] = $distanceKm;
                        $speeds[] = $distanceKm / ($elapsedSeconds / 3600);
                    }
                }
            }

            if (! empty($subject['intervals']) && count($subject['intervals']) >= 3) {
                $median = $this->percentile($subject['intervals'], 50);
                $p05 = $this->percentile($subject['intervals'], 5);

                if ($median !== null && $median > 0 && $p05 !== null) {
                    $rapidRatios[] = $p05 / $median;
                }
            }

            $subject['last_flags'] = $flags;
            $subject['last_seen_at'] = $createdAt;
            $subject['last_coordinates'] = $coordinates ?? $subject['last_coordinates'];
            unset($subject);
        }

        $subjectDeniedRatios = [];
        $subjectRequestCounts = [];
        $provinceCounts = [];
        $countryCounts = [];
        $flagChangeCounts = [];

        foreach ($subjects as $subject) {
            $subjectRequestCounts[] = $subject['requests'];
            $subjectDeniedRatios[] = $subject['requests'] > 0 ? $subject['denied'] / $subject['requests'] : 0.0;
            $provinceCounts[] = count($subject['provinces']);
            $countryCounts[] = count($subject['countries']);
            $flagChangeCounts[] = $subject['flag_changes'];
        }

        return [
            'total' => $total,
            'subjects' => count($subjects),
            'denial_ratio' => $total > 0 ? $denied / $total : 0.0,
            'evasion_ratio' => $total > 0 ? $evasionCount / $total : 0.0,
            'p75_subject_requests' => $this->percentile($subjectRequestCounts, 75),
            'p95_subject_denial_ratio' => $this->percentile($subjectDeniedRatios, 95),
            'p95_province_count' => $this->percentile($provinceCounts, 95),
            'p95_country_count' => $this->percentile($countryCounts, 95),
            'p95_flag_changes' => $this->percentile($flagChangeCounts, 95),
            'p05_rapid_ratio' => $this->percentile($rapidRatios, 5),
            'p99_speed_kmh' => $this->percentile($speeds, 99),
            'p25_distance_km' => $this->percentile($distances, 25),
            'p95_interval_seconds' => $this->percentile($intervals, 95),
            'p95_processing_ms' => $this->percentile($processingTimes, 95),
        ];
    }

    /**
     * @param  array<string, mixed>              $stats
     * @param  array<string, mixed>              $defaults
     * @return array<int, array<string, string>>
     */
    private function recommend(array $stats, array $defaults): array
    {
        $minRequests = (int) $this->clamp((float) round(($stats['p75_subject_requests'] ?? 8) ?: 8), 8, 50);
        $denialRatio = $this->clamp((float) (($stats['p95_subject_denial_ratio'] ?? 0.6) ?: 0.6) + 0.10, 0.50, 0.95);
        $provinceThreshold = (int) $this->clamp((float) ceil((($stats['p95_province_count'] ?? 4) ?: 4) + 1), 5, 18);
        $countryThreshold = (int) $this->clamp((float) ceil((($stats['p95_country_count'] ?? 2) ?: 2) + 1), 3, 10);
        $flagChanges = (int) $this->clamp((float) ceil((($stats['p95_flag_changes'] ?? 1) ?: 1) + 1), 2, 10);
        $rapidRatio = $this->clamp((float) (($stats['p05_rapid_ratio'] ?? 0.2) ?: 0.2), 0.05, 0.50);
        $travelSpeed = $this->clamp((float) (($stats['p99_speed_kmh'] ?? 1000) ?: 1000) * 1.20, 1000, 3000);
        $minDistance = $this->clamp((float) (($stats['p25_distance_km'] ?? 50) ?: 50), 25, 150);

        return [
            $this->row('min_requests_for_denial_ratio', 'ANGOLA_GEOGUARD_THREAT_MIN_REQUESTS_DENIAL', $defaults, (string) $minRequests, 'P75 pedidos por sujeito, min 8'),
            $this->row('denial_ratio_threshold', 'ANGOLA_GEOGUARD_THREAT_DENIAL_RATIO', $defaults, $this->formatDecimal($denialRatio), 'P95 racio negacao por sujeito + margem'),
            $this->row('province_enumeration_threshold', 'ANGOLA_GEOGUARD_THREAT_PROVINCE_ENUM_THRESHOLD', $defaults, (string) $provinceThreshold, 'P95 provincias distintas + 1'),
            $this->row('country_hopping_threshold', 'ANGOLA_GEOGUARD_THREAT_COUNTRY_HOP_THRESHOLD', $defaults, (string) $countryThreshold, 'P95 paises distintos + 1'),
            $this->row('evasion_cycling_min_occurrences', 'ANGOLA_GEOGUARD_THREAT_EVASION_MIN_OCCURRENCES', $defaults, (string) $flagChanges, 'P95 alternancias de sinais + 1'),
            $this->row('rapid_fire_ratio', 'ANGOLA_GEOGUARD_THREAT_RAPID_FIRE_RATIO', $defaults, $this->formatDecimal($rapidRatio), 'P05 intervalo/mediana por sujeito'),
            $this->row('impossible_travel_kmh', 'ANGOLA_GEOGUARD_THREAT_IMPOSSIBLE_TRAVEL_KMH', $defaults, $this->formatDecimal($travelSpeed), 'P99 velocidades observadas x 1.2'),
            $this->row('impossible_travel_min_distance_km', 'ANGOLA_GEOGUARD_THREAT_IMPOSSIBLE_TRAVEL_MIN_KM', $defaults, $this->formatDecimal($minDistance), 'P25 distancias entre localizacoes'),
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, string>
     */
    private function row(string $key, string $env, array $defaults, string $suggested, string $basis): array
    {
        return [
            'key' => $key,
            'env' => $env,
            'current' => (string) ($defaults[$key] ?? '-'),
            'suggested' => $suggested,
            'basis' => $basis,
        ];
    }

    /**
     * @param AuditDecisionRow $row
     */
    private function subjectKey(object $row): string
    {
        foreach (['user_id', 'ip_address', 'tenant_id'] as $field) {
            if (isset($row->{$field}) && trim((string) $row->{$field}) !== '') {
                return (string) $row->{$field};
            }
        }

        return 'anonymous';
    }

    private function timestamp(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if ($value === null) {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }

    /**
     * @param AuditDecisionRow $row
     */
    private function coordinates(object $row): ?Coordinates
    {
        if ($row->latitude === null || $row->longitude === null) {
            return null;
        }

        try {
            return new Coordinates((float) $row->latitude, (float) $row->longitude);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, float|int> $values
     */
    private function percentile(array $values, float $percentile): ?float
    {
        $values = array_values(array_filter($values, fn (float|int $value): bool => is_finite((float) $value)));

        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $index = ($percentile / 100) * (count($values) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return (float) $values[$lower];
        }

        $weight = $index - $lower;

        return ((float) $values[$lower] * (1 - $weight)) + ((float) $values[$upper] * $weight);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return min($max, max($min, $value));
    }

    private function formatRatio(float $ratio): string
    {
        return sprintf('%.2f%%', $ratio * 100);
    }

    private function formatDecimal(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
    }

    private function formatSeconds(?float $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        if ($seconds < 60) {
            return sprintf('%ds', (int) round($seconds));
        }

        return sprintf('%.1f min', $seconds / 60);
    }
}
