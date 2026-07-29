<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Contracts;

/**
 * Contrato minimo de acesso a base de dados exigido pelos adaptadores
 * espaciais (PostGIS/MySQL Spatial). Mantem o pacote desacoplado de
 * uma unica biblioteca de acesso a dados; em Laravel, a implementacao
 * concreta e um wrapper fino sobre Illuminate\Database\ConnectionInterface.
 */
interface DatabaseConnectionInterface
{
    /**
     * Executa uma query de selecao e devolve a primeira linha como array
     * associativo, ou null se nao houver resultado.
     *
     * @param array<int, mixed> $bindings
     */
    public function selectOne(string $query, array $bindings = []): ?array;
}
