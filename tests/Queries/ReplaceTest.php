<?php

declare(strict_types=1);

require __DIR__ . '/../_resources/init.php';

use PHPUnit\Framework\TestCase;
use Envms\FluentPDO\Query;

/**
 * Class ReplaceTest
 *
 * @covers \Envms\FluentPDO\Queries\Replace
 */
class ReplaceTest extends TestCase
{

    /** @var Envms\FluentPDO\Query */
    protected $fluent;

    public function setUp(): void
    {
        global $pdo;

        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_BOTH);

        $this->fluent = new Query($pdo);
    }

    public function testReplaceStatement()
    {
        $query = $this->fluent->replaceInto('article', [
            'user_id' => 1,
            'title'   => 'new title',
            'content' => 'new content'
        ]);

        self::assertEquals('REPLACE INTO article (user_id, title, content) VALUES (?, ?, ?)', $query->getQuery(false));
        self::assertEquals(['0' => '1', '1' => 'new title', '2' => 'new content'], $query->getParameters());
    }

    public function testReplaceUnsupportedDialect()
    {
        // Create a mock PDO for an unsupported dialect
        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('getAttribute')
            ->willReturn('sqlsrv'); // SQL Server doesn't support upsert

        $fluent = new Query($mockPdo);

        $this->expectException(\Envms\FluentPDO\Exception::class);
        $this->expectExceptionMessage('REPLACE INTO / INSERT OR REPLACE is not supported by this database dialect');

        $fluent->replaceInto('article', ['title' => 'test']);
    }
}