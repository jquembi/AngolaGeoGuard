<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Enums;

/**
 * Modos de funcionamento do motor de controlo geoespacial.
 *
 * Cada modo determina a estrategia de avaliacao utilizada pelo
 * GeoAccessPolicyEngine ao decidir se um pedido deve ser permitido.
 */
enum AccessMode: string
{
    case GLOBAL = 'global';
    case ANGOLA_ONLY = 'angola_only';
    case PROVINCE_ONLY = 'province_only';
    case MULTIPLE_PROVINCES = 'multiple_provinces';
    case CUSTOM_GEOFENCE = 'custom_geofence';
    case BLOCKLIST = 'blocklist';
    case ALLOWLIST = 'allowlist';
    case HYBRID = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Acesso global',
            self::ANGOLA_ONLY => 'Apenas Angola',
            self::PROVINCE_ONLY => 'Apenas uma provincia',
            self::MULTIPLE_PROVINCES => 'Multiplas provincias',
            self::CUSTOM_GEOFENCE => 'Geofence personalizado',
            self::BLOCKLIST => 'Lista de bloqueio',
            self::ALLOWLIST => 'Lista de permissao',
            self::HYBRID => 'Regras hibridas',
        };
    }

    /**
     * Indica se este modo depende da resolucao previa da provincia
     * a partir das coordenadas do utilizador.
     */
    public function requiresProvinceResolution(): bool
    {
        return match ($this) {
            self::PROVINCE_ONLY, self::MULTIPLE_PROVINCES, self::HYBRID => true,
            default => false,
        };
    }
}
