<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Tests;

require __DIR__ . '/_resources/init.php';

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Envms\FluentPDO\Query;

class MemoryTest extends TestCase
{
    #[Test]
    public function queryStateIsClearedAfterExecution(): void
    {
        global $pdo;
        $fluent = new Query($pdo);

        $query = $fluent->from('user')->where('id', 1);

        // Get reflection to check private properties
        $reflection = new \ReflectionClass($query);
        $statementsProperty = $reflection->getProperty('statements');
        $statementsProperty->setAccessible(true);

        // Before execution, should have statements
        $this->assertNotEmpty($statementsProperty->getValue($query));

        $query->execute();

        // After execution, statements should be cleared
        $this->assertEmpty($statementsProperty->getValue($query));
    }

    #[Test]
    public function chunkProcessingReducesMemoryUsage(): void
    {
        global $pdo;
        $fluent = new Query($pdo);

        $memoryBefore = memory_get_usage();

        $query = $fluent->from('user');
        $result = $query->execute();
        $allRows = $result->fetchAll();
        $rows = count($allRows);

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        // For now, just check that we can fetch data
        // TODO: Implement proper chunking test
        $this->assertGreaterThan(0, $rows);
        $this->assertLessThan(1024 * 1024, $memoryUsed);
    }
}