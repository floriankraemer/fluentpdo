<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Queries;

use PDO;
use PDOStatement;
use Iterator;
use Countable;

/**
 * Result wrapper that provides memory-efficient iteration
 *
 * @implements Iterator<int, array<string, mixed>|object|null>
 */
class Result implements Iterator, Countable
{
    private int $position = 0;

    /** @var array<string, mixed>|object|null */
    private $currentRow = null;

    /** @var int<0, max>|null */
    private ?int $cachedCount = null;

    public function __construct(
        private readonly PDOStatement $statement,
        private readonly int $fetchMode = PDO::FETCH_ASSOC,
        private readonly bool $convertTypes = false
    ) {
    }

    public function isConvertTypes(): bool
    {
        return $this->convertTypes;
    }

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
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(?int $fetchMode = null): array
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
        while (($row = $this->statement->fetch($this->fetchMode)) !== false) {
            $chunk[] = $row;

            if (count($chunk) >= $size) {
                $callback($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
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
        $fetched = $this->statement->fetch($this->fetchMode);
        $this->currentRow = $fetched === false ? null : $fetched;
        $this->position++;
    }

    public function valid(): bool
    {
        return $this->currentRow !== null;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        if ($this->cachedCount !== null) {
            return $this->cachedCount;
        }

        $this->cachedCount = max(0, $this->statement->rowCount());

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