<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Contracts;

/**
 * Contrato minimo que uma aplicacao host deve implementar para que
 * o pacote reconheca o tenant atual, sem depender de nenhuma
 * biblioteca especifica de tenancy. Ver secao 16.
 */
interface TenantContextInterface
{
    public function tenantId(): string;

    public function tenantSlug(): ?string;

    /**
     * Dados de configuracao geoespacial especificos do tenant
     * (modo, provincias permitidas, geofences, etc), ja resolvidos
     * pela aplicacao host a partir da sua propria fonte de dados.
     */
    public function geoConfig(): array;
}
