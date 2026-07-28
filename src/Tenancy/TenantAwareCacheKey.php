<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tenancy;

/**
 * Constroi chaves de cache que isolam dados por tenant e incluem a
 * versao dos dados/politica, evitando fuga de politicas/geofences
 * entre tenants e servir decisoes desatualizadas apos uma alteracao.
 * Ver secoes 16 e 23.
 *
 * Formato: geoguard:{tenant}:{data_version}:{policy_version}:{ip_hash}
 */
final class TenantAwareCacheKey
{
    public static function build(
        ?string $tenantId,
        string $dataVersion,
        string $policyVersion,
        string $ipAddress,
    ): string {
        $prefix = config('angola-geoguard.cache.prefix', 'geoguard');
        $tenant = $tenantId ?? 'default';
        $ipHash = hash('sha256', $ipAddress);

        return sprintf('%s:%s:%s:%s:%s', $prefix, $tenant, $dataVersion, $policyVersion, $ipHash);
    }

    /**
     * Chave para configuracao geografica de um tenant especifico.
     */
    public static function forTenantConfig(string $tenantId): string
    {
        $prefix = config('angola-geoguard.cache.prefix', 'geoguard');

        return sprintf('%s:tenant-config:%s', $prefix, $tenantId);
    }
}
