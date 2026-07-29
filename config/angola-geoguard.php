<?php

declare(strict_types=1);

use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Enums\FailureMode;

return [

    'enabled' => env('ANGOLA_GEOGUARD_ENABLED', true),

    'default_mode' => env('ANGOLA_GEOGUARD_DEFAULT_MODE', AccessMode::ANGOLA_ONLY->value),

    'country' => [
        'code' => 'AO',
        'name' => 'Angola',
    ],

    'default_province' => env('ANGOLA_GEOGUARD_DEFAULT_PROVINCE'),

    'allowed_provinces' => [],

    'blocked_provinces' => [],

    'location' => [
        'minimum_confidence' => env('ANGOLA_GEOGUARD_MIN_CONFIDENCE', 'medium'),
        'cache_ttl' => env('ANGOLA_GEOGUARD_CACHE_TTL', 3600),
        'require_coordinates' => env('ANGOLA_GEOGUARD_REQUIRE_COORDINATES', false),
        'trust_user_input' => env('ANGOLA_GEOGUARD_TRUST_USER_INPUT', false),
    ],

    'security' => [
        'block_vpn' => env('ANGOLA_GEOGUARD_BLOCK_VPN', false),
        'block_proxy' => env('ANGOLA_GEOGUARD_BLOCK_PROXY', false),
        'block_tor' => env('ANGOLA_GEOGUARD_BLOCK_TOR', true),
        'block_datacenter_ip' => env('ANGOLA_GEOGUARD_BLOCK_DATACENTER', false),
        'detect_location_mismatch' => env('ANGOLA_GEOGUARD_DETECT_MISMATCH', true),
        'maximum_location_age_minutes' => env('ANGOLA_GEOGUARD_MAX_LOCATION_AGE', 60),

        // CIDRs de proxies/load balancers confiaveis. So os cabecalhos
        // X-Forwarded-For / CF-Connecting-IP / True-Client-IP vindos
        // destes CIDRs sao considerados (ver secao 14).
        'trusted_proxies' => array_filter(explode(',', (string) env('ANGOLA_GEOGUARD_TRUSTED_PROXIES', ''))),

        'location_token' => [
            // Chave usada para assinar tokens de localizacao com HMAC.
            'key' => env('ANGOLA_GEOGUARD_TOKEN_KEY'),
            'key_version' => env('ANGOLA_GEOGUARD_TOKEN_KEY_VERSION', 'v1'),
            'ttl_seconds' => env('ANGOLA_GEOGUARD_TOKEN_TTL', 300),
        ],
    ],

    'failure_mode' => env('ANGOLA_GEOGUARD_FAILURE_MODE', FailureMode::DENY->value),

    'observation_mode' => env('ANGOLA_GEOGUARD_OBSERVATION_MODE', false),

    'audit' => [
        'enabled' => env('ANGOLA_GEOGUARD_AUDIT_ENABLED', true),
        'retention_days' => env('ANGOLA_GEOGUARD_AUDIT_RETENTION_DAYS', 365),
        'store_ip' => env('ANGOLA_GEOGUARD_AUDIT_STORE_IP', true),
        'anonymize_ip_after_days' => env('ANGOLA_GEOGUARD_AUDIT_ANONYMIZE_AFTER_DAYS', 30),
        'store_coordinates' => env('ANGOLA_GEOGUARD_AUDIT_STORE_COORDINATES', false),
        'round_coordinates_to' => env('ANGOLA_GEOGUARD_AUDIT_ROUND_COORDINATES', 3),
        'hash_chain_enabled' => env('ANGOLA_GEOGUARD_AUDIT_HASH_CHAIN', false),
    ],

    'tenancy' => [
        'enabled' => env('ANGOLA_GEOGUARD_TENANCY_ENABLED', false),

        // FQCN de uma classe que implementa TenantContextInterface,
        // ou null para resolver via container/contexto da app host.
        'tenant_resolver' => null,
    ],

    'spatial' => [
        // memory | postgis | mysql | mariadb
        'engine' => env('ANGOLA_GEOGUARD_SPATIAL_ENGINE', 'memory'),

        // Tolerancia em metros para pontos proximos de fronteiras.
        'boundary_tolerance_meters' => env('ANGOLA_GEOGUARD_BOUNDARY_TOLERANCE', 0),
    ],

    // Deteccao comportamental / contramedidas (ver ThreatScorer,
    // CountermeasureEngine e BehaviorTrackingService). Heuristica e
    // estatistica (linha de base EWMA + regras de limiar), NAO um
    // modelo de machine learning — ver docblock de ThreatScorer.
    'threat_detection' => [
        'enabled' => env('ANGOLA_GEOGUARD_THREAT_DETECTION_ENABLED', true),

        // Duracao da janela deslizante usada para contadores de
        // rajada/enumeracao (racio de negacao, provincias/paises
        // distintos). Reinicia periodicamente; NAO afeta a memoria
        // de longo prazo (violation_count, linha de base EWMA).
        'window_minutes' => env('ANGOLA_GEOGUARD_THREAT_WINDOW_MINUTES', 15),

        'max_tracked_provinces' => 30,
        'max_tracked_countries' => 10,

        // Limiares e pesos individuais de cada sinal do ThreatScorer.
        // Ver ThreatScorerConfig para o raciocinio de calibracao de
        // cada valor por defeito (reducao de falsos positivos).
        'thresholds' => [
            'impossible_travel_kmh' => env('ANGOLA_GEOGUARD_THREAT_IMPOSSIBLE_TRAVEL_KMH', 1000.0),
            'impossible_travel_min_distance_km' => env('ANGOLA_GEOGUARD_THREAT_IMPOSSIBLE_TRAVEL_MIN_KM', 50.0),
            'impossible_travel_weight' => env('ANGOLA_GEOGUARD_THREAT_IMPOSSIBLE_TRAVEL_WEIGHT', 40),

            'rapid_fire_ratio' => env('ANGOLA_GEOGUARD_THREAT_RAPID_FIRE_RATIO', 0.2),
            'rapid_fire_min_baseline_samples' => env('ANGOLA_GEOGUARD_THREAT_RAPID_FIRE_MIN_SAMPLES', 3),
            'rapid_fire_weight' => env('ANGOLA_GEOGUARD_THREAT_RAPID_FIRE_WEIGHT', 15),

            'min_requests_for_denial_ratio' => env('ANGOLA_GEOGUARD_THREAT_MIN_REQUESTS_DENIAL', 8),
            'denial_ratio_threshold' => env('ANGOLA_GEOGUARD_THREAT_DENIAL_RATIO', 0.6),
            'denial_ratio_weight' => env('ANGOLA_GEOGUARD_THREAT_DENIAL_RATIO_WEIGHT', 25),

            'province_enumeration_threshold' => env('ANGOLA_GEOGUARD_THREAT_PROVINCE_ENUM_THRESHOLD', 5),
            'province_enumeration_weight' => env('ANGOLA_GEOGUARD_THREAT_PROVINCE_ENUM_WEIGHT', 20),

            'country_hopping_threshold' => env('ANGOLA_GEOGUARD_THREAT_COUNTRY_HOP_THRESHOLD', 3),
            'country_hopping_weight' => env('ANGOLA_GEOGUARD_THREAT_COUNTRY_HOP_WEIGHT', 25),

            'evasion_cycling_min_occurrences' => env('ANGOLA_GEOGUARD_THREAT_EVASION_MIN_OCCURRENCES', 2),
            'evasion_cycling_weight' => env('ANGOLA_GEOGUARD_THREAT_EVASION_WEIGHT', 15),

            'ewma_alpha' => env('ANGOLA_GEOGUARD_THREAT_EWMA_ALPHA', 0.3),

            // Pontuacao (0-100) a partir da qual cada ThreatLevel se aplica.
            'watching_score_threshold' => env('ANGOLA_GEOGUARD_THREAT_WATCHING_THRESHOLD', 20),
            'suspicious_score_threshold' => env('ANGOLA_GEOGUARD_THREAT_SUSPICIOUS_THRESHOLD', 45),
            'confirmed_threat_score_threshold' => env('ANGOLA_GEOGUARD_THREAT_CONFIRMED_THRESHOLD', 70),
        ],

        // Duracao da primeira quarentena aplicada a um sujeito.
        'base_quarantine_seconds' => env('ANGOLA_GEOGUARD_THREAT_BASE_QUARANTINE', 900),

        // Teto maximo de duracao, independentemente do numero de violacoes.
        'max_quarantine_seconds' => env('ANGOLA_GEOGUARD_THREAT_MAX_QUARANTINE', 86400),

        // Fator multiplicativo de escalonamento por violacao repetida
        // (memoria de longo prazo). duracao = base * (fator ^ violacoes_anteriores).
        'escalation_factor' => env('ANGOLA_GEOGUARD_THREAT_ESCALATION_FACTOR', 2.0),

        // Apos quantos dias sem atividade um perfil comportamental
        // (sem quarentena ativa) pode ser removido por geoguard:prune.
        'profile_retention_days' => env('ANGOLA_GEOGUARD_THREAT_PROFILE_RETENTION_DAYS', 90),
    ],

    'providers' => [
        'default' => env('ANGOLA_GEOGUARD_PROVIDER', 'chain'),

        'chain' => [
            'local_geoip',
            'maxmind',
            'remote_api',
        ],

        'failover' => env('ANGOLA_GEOGUARD_PROVIDER_FAILOVER', true),
        'timeout_seconds' => env('ANGOLA_GEOGUARD_PROVIDER_TIMEOUT', 3),
        'cache_ttl' => env('ANGOLA_GEOGUARD_PROVIDER_CACHE_TTL', 3600),

        'maxmind' => [
            'database_path' => env('ANGOLA_GEOGUARD_MAXMIND_DB_PATH'),
        ],

        'remote_api' => [
            'base_url' => env('ANGOLA_GEOGUARD_REMOTE_API_URL'),
            'api_key' => env('ANGOLA_GEOGUARD_REMOTE_API_KEY'),
        ],
    ],

    'cache' => [
        'store' => env('ANGOLA_GEOGUARD_CACHE_STORE'), // null = usa o store default da app
        'prefix' => 'geoguard',
    ],

    'responses' => [
        'status_code' => env('ANGOLA_GEOGUARD_RESPONSE_STATUS', 403),
        'view' => 'angola-geoguard::blocked',
        'json_message' => 'Acesso nao permitido nesta localizacao.',
    ],

    // Dados geograficos: fonte e versao ativas dos limites administrativos.
    // Nao deve ser alterado silenciosamente — use `php artisan geoguard:import`.
    'data' => [
        'active_boundaries_version' => env('ANGOLA_GEOGUARD_BOUNDARIES_VERSION'),
        'storage_disk' => env('ANGOLA_GEOGUARD_STORAGE_DISK', 'local'),
    ],
];
