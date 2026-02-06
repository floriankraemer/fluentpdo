<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Dialect;

use PDO;

class DialectFactory
{
    public static function create(PDO $pdo): DialectInterface
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match($driver) {
            'mysql' => new MySQLDialect($pdo),
            'pgsql' => new PostgreSQLDialect($pdo),
            'sqlite' => new SQLiteDialect($pdo),
            'sqlsrv', 'odbc' => new SQLServerDialect($pdo),
            default => throw new \RuntimeException("Unsupported database driver: $driver")
        };
    }
}