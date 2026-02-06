<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Benchmarks;

use Envms\FluentPDO\Query;

class QueryBuilderBench
{
    private \PDO $pdo;
    private Query $fluent;

    public function __construct()
    {
        // Initialize database connection for benchmarking
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create test table
        $this->pdo->exec('
            CREATE TABLE user (
                id INTEGER PRIMARY KEY,
                name TEXT,
                email TEXT,
                country_id INTEGER
            )
        ');

        // Insert test data
        $stmt = $this->pdo->prepare('INSERT INTO user (name, email, country_id) VALUES (?, ?, ?)');
        for ($i = 1; $i <= 1000; $i++) {
            $stmt->execute(["User{$i}", "user{$i}@example.com", rand(1, 10)]);
        }

        $this->fluent = new Query($this->pdo);
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchSimpleSelect(): void
    {
        $result = $this->fluent->from('user')->where('id', 1)->execute();
        $result->fetch();
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchComplexQuery(): void
    {
        $result = $this->fluent
            ->from('user')
            ->select('user.name, user.email')
            ->where('country_id', 1)
            ->orderBy('name')
            ->limit(10)
            ->execute();

        $result->fetchAll();
    }

    /**
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchQueryBuilding(): void
    {
        // Test query building performance without execution
        $query = $this->fluent
            ->from('user')
            ->select('user.*')
            ->where('country_id > ?', 5)
            ->orderBy('name DESC')
            ->limit(50);

        $sql = $query->getQuery();
        $params = $query->getParameters();
    }

    /**
     * @Revs(100)
     * @Iterations(3)
     */
    public function benchLargeResultSet(): void
    {
        $result = $this->fluent->from('user')->execute();
        $count = 0;
        foreach ($result as $row) {
            $count++;
        }
    }
}