<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Envms\FluentPDO\Dialect\{MySQLDialect, PostgreSQLDialect, SQLiteDialect, SQLServerDialect};

#[CoversClass(MySQLDialect::class)]
#[CoversClass(PostgreSQLDialect::class)]
#[CoversClass(SQLiteDialect::class)]
#[CoversClass(SQLServerDialect::class)]
class DialectTest extends TestCase
{
    #[Test]
    #[DataProvider('identifierProvider')]
    public function quoteIdentifier(string $dialect, string $input, string $expected): void
    {
        $pdo = $this->createMock(\PDO::class);
        $instance = new $dialect($pdo);

        $this->assertEquals($expected, $instance->quoteIdentifier($input));
    }

    public static function identifierProvider(): array
    {
        return [
            'mysql_simple' => [MySQLDialect::class, 'table', '`table`'],
            'mysql_dotted' => [MySQLDialect::class, 'schema.table', '`schema`.`table`'],
            'pgsql_simple' => [PostgreSQLDialect::class, 'table', '"table"'],
            'sqlite_simple' => [SQLiteDialect::class, 'table', '"table"'],
            'sqlserver_simple' => [SQLServerDialect::class, 'table', '[table]'],
        ];
    }

    // Add more dialect-specific tests...
}