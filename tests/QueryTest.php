<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Envms\FluentPDO\Query;

class QueryTest extends TestCase
{
    #[Test]
    public function transactionCommitsOnSuccess(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        $fluent = new Query($pdo);

        $result = $fluent->transaction(function (Query $db) {
            return 'success';
        });

        $this->assertEquals('success', $result);
    }

    #[Test]
    public function transactionRollsBackOnException(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->once())->method('rollBack');

        $fluent = new Query($pdo);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test error');

        $fluent->transaction(function (Query $db) {
            throw new \Exception('Test error');
        });
    }

    #[Test]
    public function transactionReturnsCallbackResult(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        $fluent = new Query($pdo);

        $result = $fluent->transaction(function (Query $db) {
            return ['data' => 'value', 'count' => 42];
        });

        $this->assertEquals(['data' => 'value', 'count' => 42], $result);
    }

    #[Test]
    public function transactionJoinsExistingTransaction(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $pdo->expects($this->never())->method('beginTransaction');
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        $fluent = new Query($pdo);

        $result = $fluent->transaction(function (Query $db) {
            return 'nested_result';
        });

        $this->assertEquals('nested_result', $result);
    }

    #[Test]
    public function transactionPassesQueryInstanceToCallback(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');

        $fluent = new Query($pdo);

        $result = $fluent->transaction(function (Query $db) use ($fluent) {
            // Verify the callback receives the same Query instance
            $this->assertSame($fluent, $db);
            return 'verified';
        });

        $this->assertEquals('verified', $result);
    }
}