<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Queries;

use Envms\FluentPDO\{Exception, Literal, Query};

/** INSERT query builder
 */
class Insert extends Base
{

    /** @var array<int, string> */
    private $columns = [];

    /** @var array<int|string, mixed> */
    private $firstValue = [];

    /** @var bool */
    private $ignore = false;
    /** @var bool */
    private $delayed = false;

    /**
     * InsertQuery constructor.
     *
     * @param Query  $fluent
     * @param string $table
     * @param array<int|string, mixed> $values
     *
     * @throws Exception
     */
    public function __construct(Query $fluent, string $table, array $values)
    {
        $clauses = [
            'INSERT INTO'             => [$this, 'getClauseInsertInto'],
            'VALUES'                  => [$this, 'getClauseValues'],
            'ON DUPLICATE KEY UPDATE' => [$this, 'getClauseOnDuplicateKeyUpdate'],
        ];
        parent::__construct($fluent, $clauses);

        $this->statements['INSERT INTO'] = $table;
        $this->values($values);
    }

    /**
     * Force insert operation to fail silently
     *
     * @return Insert
     */
    public function ignore()
    {
        $this->ignore = true;

        return $this;
    }

    /** Force insert operation delay support
     *
     * @return Insert
     */
    public function delayed()
    {
        $this->delayed = true;

        return $this;
    }

    /**
     * Add VALUES
     *
     * @param array<int|string, mixed> $values
     *
     * @return Insert
     * @throws Exception
     */
    public function values(array $values): self
    {
        $first = current($values);
        $firstKey = key($values);

        if (is_string($firstKey)) {
            $this->addOneValue($values);
            return $this;
        }

        if (is_array($first) && is_string(key($first))) {
            foreach ($values as $oneValue) {
                $this->addOneValue($oneValue);
            }
        }

        return $this;
    }

    /**
     * Add ON DUPLICATE KEY UPDATE
     *
     * @param array<string, mixed> $values
     *
     * @return Insert
     */
    public function onDuplicateKeyUpdate(array $values): self
    {
        $this->statements['ON DUPLICATE KEY UPDATE'] = array_merge(
            $this->statements['ON DUPLICATE KEY UPDATE'], $values
        );

        return $this;
    }

    /**
     * Execute insert query
     *
     * @param string|null $sequence
     *
     * @throws Exception
     *
     * @return int|string|false - Last inserted primary key (string for large integers)
     */
    public function execute(mixed $sequence = null): int|string|false
    {
        $result = parent::execute();

        if (!$result instanceof \PDOStatement && !$result instanceof Result) {
            return false;
        }

        return $this->fluent->getPdo()->lastInsertId($sequence);
    }

    /**
     * @param null $sequence
     *
     * @throws Exception
     *
     * @return bool
     */
    public function executeWithoutId($sequence = null): bool
    {
        return (bool) parent::execute();
    }

    /**
     * @return string
     */
    protected function getClauseInsertInto()
    {
        return 'INSERT' . ($this->ignore ? " IGNORE" : '') . ($this->delayed ? " DELAYED" : '') . ' INTO ' . $this->statements['INSERT INTO'];
    }

    /**
     * @return string
     */
    protected function getClauseValues()
    {
        $valuesArray = [];
        foreach ($this->statements['VALUES'] as $rows) {
            // literals should not be parametrized.
            // They are commonly used to call engine functions or literals.
            // Eg: NOW(), CURRENT_TIMESTAMP etc
            $placeholders = array_map([$this, 'parameterGetValue'], $rows);
            $valuesArray[] = '(' . implode(', ', $placeholders) . ')';
        }

        $columns = implode(', ', $this->columns);
        $values = implode(', ', $valuesArray);

        return " ($columns) VALUES $values";
    }


    /**
     * @return string
     */
    protected function getClauseOnDuplicateKeyUpdate()
    {
        $result = [];
        foreach ($this->statements['ON DUPLICATE KEY UPDATE'] as $key => $value) {
            $result[] = "$key = " . $this->parameterGetValue($value);
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $result);
    }

    /**
     * @param mixed $param
     *
     * @return string
     */
    protected function parameterGetValue(mixed $param): string
    {
        return $param instanceof Literal ? (string)$param : '?';
    }

    /**
     * Removes all Literal instances from the argument
     * since they are not to be used as PDO parameters but rather injected directly into the query
     *
     * @param array<int|string, mixed> $statements
     *
     * @return array<int|string, mixed>
     */
    protected function filterLiterals(array $statements): array
    {
        $isNotLiteral = fn($item) => !$item instanceof Literal;

        return array_map(
            fn($item) => is_array($item) ? array_filter($item, $isNotLiteral) : $item,
            array_filter($statements, $isNotLiteral)
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function buildParameters(): array
    {
        $this->parameters = array_merge(
            $this->filterLiterals($this->statements['VALUES']),
            $this->filterLiterals($this->statements['ON DUPLICATE KEY UPDATE'])
        );

        return parent::buildParameters();
    }

    /**
     * @param array<int|string, mixed> $oneValue
     *
     * @throws Exception
     */
    private function addOneValue(array $oneValue): void
    {
        foreach ($oneValue as $key => $value) {
            if (!is_string($key)) {
                throw new Exception('INSERT query: All keys of value array have to be strings.');
            }
        }

        if (!$this->firstValue) {
            $this->firstValue = $oneValue;
        }

        if (!$this->columns) {
            /** @phpstan-ignore arrayValues.list */
            $this->columns = array_values(array_map('strval', array_keys($oneValue)));
        }

        if ($this->columns != array_keys($oneValue)) {
            throw new Exception('INSERT query: All VALUES have to same keys (columns).');
        }

        $this->statements['VALUES'][] = $oneValue;
    }

}
