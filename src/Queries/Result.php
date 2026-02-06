<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Queries;

use PDO;
use PDOStatement;
use Iterator;
use Countable;

/**
 * Result wrapper that provides memory-efficient iteration
 */
class Result implements Iterator, Countable
{
    private int $position = 0;
    private ?array $currentRow = null;
    private ?int $cachedCount = null;

    public function __construct(
        private readonly PDOStatement $statement,
        private readonly int $fetchMode = PDO::FETCH_ASSOC,
        private readonly bool $convertTypes = false
    ) {}

    /**
     * Get the underlying PDOStatement
     */
    public function getStatement(): PDOStatement
    {
        return $this->statement;
    }

    /**
     * Fetch single row
     */
    public function fetch(): mixed
    {
        return $this->statement->fetch($this->fetchMode);
    }

    /**
     * Fetch all rows (use with caution - loads into memory)
     */
    public function fetchAll(int $fetchMode = null): array
    {
        return $this->statement->fetchAll($fetchMode ?? $this->fetchMode);
    }

    /**
     * Fetch single column from next row
     */
    public function fetchColumn(int $columnNumber = 0): mixed
    {
        return $this->statement->fetchColumn($columnNumber);
    }

    /**
     * Stream rows in chunks to reduce memory usage
     */
    public function chunk(int $size, callable $callback): void
    {
        $chunk = [];
        while ($row = $this->statement->fetch($this->fetchMode)) {
            $chunk[] = $row;

            if (count($chunk) >= $size) {
                $callback($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            $callback($chunk);
        }
    }

    // Iterator implementation
    public function rewind(): void
    {
        // PDOStatement cannot rewind, would need to re-execute
        $this->position = 0;
        $this->currentRow = $this->statement->fetch($this->fetchMode);
    }

    public function current(): mixed
    {
        return $this->currentRow;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->currentRow = $this->statement->fetch($this->fetchMode);
        $this->position++;
    }

    public function valid(): bool
    {
        return $this->currentRow !== false;
    }

    // Countable implementation
    public function count(): int
    {
        if ($this->cachedCount === null) {
            $this->cachedCount = $this->statement->rowCount();
        }
        return $this->cachedCount;
    }

    /**
     * Delegate rowCount to underlying PDOStatement
     */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }
}