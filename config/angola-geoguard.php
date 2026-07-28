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
