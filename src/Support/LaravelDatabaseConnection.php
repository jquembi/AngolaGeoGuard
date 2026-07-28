<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Support;

use Illuminate\Database\ConnectionInterface as IlluminateConnection;
use JoseQuembi\AngolaGeoGuard\Contracts\DatabaseConnectionInterface;

/**
 * Adaptador fino entre a ligacao de base de dados do Laravel e o
 * contrato framework-agnostic DatabaseConnectionInterface usado
 * pelos motores espaciais PostGIS/MySQL.
 */
final class LaravelDatabaseConnection implements DatabaseConnectionInterface
{
    public function __construct(
        private readonly IlluminateConnection $connection,
    ) {
    }

    public function selectOne(string $query, array $bindings = []): ?array
    {
        $result = $this->connection->selectOne($query, $bindings);

        if ($result === null) {
            return null;
        }

        return (array) $result;
    }
}
