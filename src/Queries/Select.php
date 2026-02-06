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
     * @param Query     $fluent
     * @param           $from
     */
    function __construct(Query $fluent, $from)
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
        $this->fromTable = reset($fromParts);
        $this->fromAlias = end($fromParts);

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
    public function select($columns, bool $overrideDefault = false)
    {
        if ($overrideDefault === true) {
            $this->resetClause('SELECT');
        } elseif ($columns === null) {
            return $this->resetClause('SELECT');
        }

        $this->addStatement('SELECT', $columns, []);

        return $this;
    }

    /**
     * Return table name from FROM clause
     */
    public function getFromTable()
    {
        return $this->fromTable;
    }

    /**
     * Return table alias from FROM clause
     */
    public function getFromAlias()
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
     * @return string
     */
    public function fetchColumn(int $columnNumber = 0): mixed
    {
        if (($s = $this->execute()) !== false) {
            return $s->fetchColumn($columnNumber);
        }

        return false;
    }

    /**
     * ! NEW: Fetch all as single-dimensional array (column values)
     */
    public function fetchColumnArray(int $columnNumber = 0): array
    {
        if ($this->result === null) {
            $this->execute();
        }

        $result = [];
        while ($value = $this->result->fetchColumn($columnNumber)) {
            $result[] = $value;
        }

        return $result;
    }

    /**
     * Fetch first row or column
     *
     * @param string $column - column name or empty string for the whole row
     * @param int    $cursorOrientation
     *
     * @throws Exception
     *
     * @return mixed string, array or false if there is no row
     */
    public function fetch(?string $column = null, int $cursorOrientation = \PDO::FETCH_ORI_NEXT)
    {
        if ($this->result === null) {
            $this->execute();
        }

        if ($this->result === false) {
            return false;
        }

        $row = $this->result->fetch($this->currentFetchMode, $cursorOrientation);

        if ($this->fluent->convertRead === true) {
            $row = Utilities::stringToNumeric($this->result, $row);
        }

        if ($row && $column !== null) {
            if (is_object($row)) {
                return $row->{$column};
            } else {
                return $row[$column];
            }
        }

        return $row;
    }

    /**
     * Fetch pairs
     *
     * @param $key
     * @param $value
     * @param $object
     *
     * @throws Exception
     *
     * @return array|\PDOStatement
     */
    public function fetchPairs($key, $value, $object = false)
    {
        if (($s = $this->select("$key, $value", true)->asObject($object)->execute()) !== false) {
            return $s->fetchAll(\PDO::FETCH_KEY_PAIR);
        }

        return $s;
    }

    /** Fetch all row
     *
     * @param string $index      - specify index column. Allows for data organization by field using 'field[]'
     * @param string $selectOnly - select columns which could be fetched
     *
     * @throws Exception
     *
     * @return array|bool -  fetched rows
     */
    public function fetchAll($index = '', $selectOnly = '')
    {
        $indexAsArray = strpos($index, '[]');

        if ($indexAsArray !== false) {
            $index = str_replace('[]', '', $index);
        }

        if ($selectOnly) {
            $this->select($index . ', ' . $selectOnly, true);
        }

        if ($index) {
            return $this->buildSelectData($index, $indexAsArray);
        } else {
            if (($result = $this->execute()) !== false) {
                if ($this->fluent->convertRead === true) {
                    return Utilities::stringToNumeric($result, $result->fetchAll());
                } else {
                    return $result->fetchAll();
                }
            }

            return false;
        }
    }

    /**
     * \Countable interface doesn't break current select query
     *
     * @throws Exception
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    /**
     * Add chunked fetch for large result sets
     * ! NEW METHOD
     */
    public function chunk(int $size, callable $callback): void
    {
        if ($this->result === null) {
            $this->execute();
        }

        if ($this->result instanceof Result) {
            $this->result->chunk($size, $callback);
        }
    }

    /**
     * Fix count() to use SQL COUNT instead of clone + fetchAll
     * ! CHANGE: Use SELECT COUNT(*) instead of clone + fetchAll
     */
    public function count(): int
    {
        // Build COUNT(*) query
        $countQuery = clone $this;
        $countQuery->select('COUNT(*) as count', true);
        $countQuery->limit(null);
        $countQuery->offset(null);

        $result = $countQuery->execute();
        $row = $result->fetch();

        return (int) ($row['count'] ?? 0);
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

        // Don't load all data into memory
        if ($this->result instanceof Result) {
            return $this->result;
        }

        // Fallback for PDOStatement
        while ($row = $this->result->fetch($this->currentFetchMode)) {
            yield $row;
        }
    }

    /**
     * @param $index
     * @param $indexAsArray
     *
     * @return array
     */
    private function buildSelectData($index, $indexAsArray)
    {
        $data = [];

        foreach ($this as $row) {
            if (is_object($row)) {
                $key = $row->{$index};
            } else {
                $key = $row[$index];
            }

            if ($indexAsArray) {
                $data[$key][] = $row;
            } else {
                $data[$key] = $row;
            }
        }

        return $data;
    }
}
