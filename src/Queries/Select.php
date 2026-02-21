<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Queries;

use Envms\FluentPDO\{Exception, Query, Utilities};
use Envms\FluentPDO\Queries\Result;

/**
 * SELECT query builder
 */
class Select extends Common implements \Countable
{

    /** @var mixed */
    private $fromTable;
    /** @var mixed */
    private $fromAlias;

    /**
     * SelectQuery constructor.
     *
     * @param Query  $fluent
     * @param string $from
     */
    public function __construct(Query $fluent, string $from)
    {
        $clauses = [
            'SELECT'   => ', ',
            'FROM'     => null,
            'JOIN'     => [$this, 'getClauseJoin'],
            'WHERE'    => [$this, 'getClauseWhere'],
            'GROUP BY' => ',',
            'HAVING'   => ' AND ',
            'ORDER BY' => ', ',
            'LIMIT'    => null,
            'OFFSET'   => null,
            "\n--"     => "\n--"
        ];
        parent::__construct($fluent, $clauses);

        // initialize statements
        $fromParts = explode(' ', $from);
        $this->fromTable = reset($fromParts) ?: '';
        $this->fromAlias = end($fromParts) ?: $this->fromTable;

        $this->statements['FROM'] = $from;
        $this->statements['SELECT'][] = $this->fromAlias . '.*';
        $this->joins[] = $this->fromAlias;
    }

    /**
     * @param mixed $columns
     * @param bool  $overrideDefault
     *
     * @return $this
     */
    public function select($columns, bool $overrideDefault = false): self
    {
        if ($overrideDefault) {
            $this->resetClause('SELECT');
        }
        if ($columns === null) {
            return $this->resetClause('SELECT');
        }

        $this->addStatement('SELECT', $columns, []);

        return $this;
    }

    /**
     * Return table name from FROM clause
     */
    public function getFromTable(): string
    {
        return $this->fromTable;
    }

    /**
     * Return table alias from FROM clause
     */
    public function getFromAlias(): string
    {
        return $this->fromAlias;
    }

    /**
     * Returns a single column
     *
     * @param int $columnNumber
     *
     * @throws Exception
     *
     * @return int|string|false|null
     */
    public function fetchColumn(int $columnNumber = 0): int|string|false|null
    {
        $result = $this->execute();

        if (!$result instanceof Result && !$result instanceof \PDOStatement) {
            return false;
        }

        return $result->fetchColumn($columnNumber);
    }

    /**
     * ! NEW: Fetch all as single-dimensional array (column values)
     *
     * @return array<int, int|string|null>
     */
    public function fetchColumnArray(int $columnNumber = 0): array
    {
        if ($this->result === null) {
            $this->execute();
        }

        if (!$this->result instanceof \PDOStatement) {
            return [];
        }

        $result = [];
        while (($value = $this->result->fetchColumn($columnNumber)) !== false) {
            $result[] = $value;
        }

        return $result;
    }

    /**
     * Fetch first row or column
     *
     * @param string|null $column - column name or empty string for the whole row
     * @param int    $cursorOrientation
     *
     * @throws Exception
     *
     * @return mixed string, array or false if there is no row
     */
    public function fetch(?string $column = null, int $cursorOrientation = \PDO::FETCH_ORI_NEXT): mixed
    {
        if ($this->result === null) {
            $this->execute();
        }

        if (!$this->result instanceof \PDOStatement) {
            return false;
        }

        $stmt = $this->result;
        $row = $stmt->fetch($this->currentFetchMode, $cursorOrientation);

        if ($this->fluent->convertRead && $row !== false) {
            $row = Utilities::stringToNumeric($stmt, $row);
        }

        if ($column !== null && $row !== false) {
            return is_object($row) ? $row->{$column} : $row[$column];
        }

        return $row;
    }

    /**
     * Fetch pairs
     *
     * @param string $key
     * @param string $value
     * @param bool $object
     *
     * @throws Exception
     *
     * @return array<int|string, mixed>|Result|\PDOStatement|false
     */
    public function fetchPairs(string $key, string $value, bool $object = false): array|Result|\PDOStatement|false
    {
        $result = $this->select("$key, $value", true)->asObject($object)->execute();

        if (!$result instanceof Result && !$result instanceof \PDOStatement) {
            return false;
        }

        return $result->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Fetch all row
     *
     * @param string $index      - specify index column. Allows for data organization by field using 'field[]'
     * @param string $selectOnly - select columns which could be fetched
     *
     * @throws Exception
     *
     * @return array<int|string, mixed>|false -  fetched rows
     */
    public function fetchAll(string $index = '', string $selectOnly = ''): array|false
    {
        $indexAsArray = str_contains($index, '[]');
        $index = str_replace('[]', '', $index);

        if ($selectOnly !== '') {
            $this->select($index . ', ' . $selectOnly, true);
        }

        if ($index !== '') {
            return $this->buildSelectData($index, $indexAsArray);
        }

        $result = $this->execute();

        if (!$result instanceof Result && !$result instanceof \PDOStatement) {
            return false;
        }

        $stmt = $result instanceof Result ? $result->getStatement() : $result;
        $rows = $result instanceof Result
            ? $result->fetchAll()
            : $result->fetchAll(\PDO::FETCH_ASSOC);

        if (!$this->fluent->convertRead) {
            return $rows;
        }

        $converted = Utilities::stringToNumeric($stmt, $rows);

        return is_array($converted) ? $converted : iterator_to_array($converted);
    }

    /**
     * \Countable interface doesn't break current select query
     *
     * @throws Exception
     *
     * @return int
     */
    /**
     * Add chunked fetch for large result sets
     * ! NEW METHOD
     */
    public function chunk(int $size, callable $callback): void
    {
        if ($this->result === null) {
            $this->execute();
        }

        if (!$this->result instanceof \PDOStatement) {
            return;
        }

        $chunk = [];
        while (($row = $this->result->fetch($this->currentFetchMode)) !== false) {
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

    /**
     * Fix count() to use SQL COUNT instead of clone + fetchAll
     * ! CHANGE: Use SELECT COUNT(*) instead of clone + fetchAll
     *
     * @return int<0, max>
     */
    public function count(): int
    {
        $countQuery = clone $this;
        $countQuery->select('COUNT(*) as count', true);
        $countQuery->resetClause('LIMIT');
        $countQuery->resetClause('OFFSET');

        $result = $countQuery->execute();

        if (!$result instanceof Result) {
            return 0;
        }

        $countVal = $result->fetchColumn(0);

        return $countVal === false || $countVal === null ? 0 : max(0, (int) $countVal);
    }

    /**
     * Use iterator for memory-efficient fetching
     * ! CHANGE: Don't call fetchAll() in getIterator
     */
    public function getIterator(): \Traversable
    {
        if ($this->result === null) {
            $this->execute();
        }

        // Use PDOStatement for memory-efficient iteration
        if ($this->result instanceof \PDOStatement) {
            while (($row = $this->result->fetch($this->currentFetchMode)) !== false) {
                yield $row;
            }
        }
    }

    /**
     * @param string $index
     * @param bool|int $indexAsArray
     *
     * @return array<int|string, mixed>
     */
    private function buildSelectData(string $index, bool|int $indexAsArray): array
    {
        $data = [];

        foreach ($this as $row) {
            if (is_array($row)) {
                $key = $row[$index] ?? null;
            } else {
                $key = $row->{$index} ?? null;
            }

            if ($key === null) {
                continue;
            }

            if ($indexAsArray) {
                if (!isset($data[$key]) || !is_array($data[$key])) {
                    $data[$key] = [];
                }
                $data[$key][] = $row;
            } else {
                $data[$key] = $row;
            }
        }

        return $data;
    }
}
