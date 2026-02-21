<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Queries;

use Envms\FluentPDO\{Exception, Literal, Utilities};

/**
 * CommonQuery add JOIN and WHERE clauses for (SELECT, UPDATE, DELETE)
 *
 * @method $this from(string $table) - add FROM to DELETE query
 * @method $this leftJoin(string $statement) - add LEFT JOIN to query
 *         $statement can be the 'table' name only or 'table:' to back reference the join
 * @method $this rightJoin(string $statement) - add RIGHT JOIN to query
 * @method $this innerJoin(string $statement) - add INNER JOIN to query
 * @method $this outerJoin(string $statement) - add OUTER JOIN to query
 * @method $this fullJoin(string $statement) - add FULL JOIN to query
 * @method $this group(string $column) - add GROUP BY to query
 * @method $this groupBy(string $column) - add GROUP BY to query
 * @method $this having(string $column) - add HAVING query
 * @method $this order(string $column) - add ORDER BY to query
 * @method $this orderBy(string $column) - add ORDER BY to query
 * @method $this limit(int $limit) - add LIMIT to query
 * @method $this offset(int $offset) - add OFFSET to query
 * @method $this comment(string $comment) - add COMMENT (--) to query
 */
abstract class Common extends Base
{

    /** @var array<int, string> - methods which are allowed to be called by the magic method __call() */
    private $validMethods = [
        'comment',
        'from',
        'fullJoin',
        'group',
        'groupBy',
        'having',
        'innerJoin',
        'join',
        'leftJoin',
        'limit',
        'offset',
        'order',
        'orderBy',
        'outerJoin',
        'rightJoin'
    ];

    /** @var array<int, string> - Query tables (also include table from clause FROM) */
    protected $joins = [];

    /** @var bool - Disable adding undefined joins to query? */
    protected $isSmartJoinEnabled = true;

    /**
     * @param string $name
     * @param array<int, mixed> $parameters - first is $statement followed by $parameters
     *
     * @return $this
     */
    public function __call(string $name, array $parameters = [])
    {
        if (!in_array($name, $this->validMethods)) {
            trigger_error("Call to invalid method " . get_class($this) . "::{$name}()", E_USER_ERROR);
        }

        $clause = Utilities::toUpperWords($name);
        $clause = match ($clause) {
            'GROUP', 'ORDER' => "{$clause} BY",
            'COMMENT' => "\n--",
            default => $clause,
        };

        $statement = array_shift($parameters);

        if (str_contains($clause, 'JOIN')) {
            return $this->addJoinStatements($clause, $statement, $parameters);
        }

        return $this->addStatement($clause, $statement, $parameters);
    }

    /**
     * @return $this
     */
    public function enableSmartJoin()
    {
        $this->isSmartJoinEnabled = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableSmartJoin()
    {
        $this->isSmartJoinEnabled = false;

        return $this;
    }

    /**
     * @return bool
     */
    public function isSmartJoinEnabled()
    {
        return $this->isSmartJoinEnabled;
    }

    /**
     * Add where condition, defaults to appending with AND
     *
     * @param string|array<string, mixed>|null $condition  - possibly containing ? or :name (PDO syntax)
     * @param mixed        $parameters
     * @param string       $separator - should be AND or OR
     *
     * @return $this
     */
    public function where(string|array|null $condition, mixed $parameters = [], string $separator = 'AND')
    {
        if ($condition === null) {
            return $this->resetClause('WHERE');
        }

        if (!$condition) {
            return $this;
        }

        if (is_array($condition)) {
            foreach ($condition as $key => $val) {
                $this->where($key, $val);
            }
            return $this;
        }

        $args = func_get_args();

        if ($parameters === []) {
            return $this->addWhereStatement($condition, $separator);
        }

        $hasParameterPlaceholder = $this->regex->sqlParameter($condition);
        if (count($args) >= 2 && !$hasParameterPlaceholder) {
            [$builtCondition, $builtParams] = $this->buildConditionFromValue($condition, $parameters, $args);
            return $this->addWhereStatement($builtCondition, $separator, $builtParams);
        }

        $params = is_array($parameters) ? $parameters : [$args[1]];
        return $this->addWhereStatement($condition, $separator, $params);
    }

    /**
     * @param array<int, mixed> $args
     * @return array{0: string, 1: array<int|string, mixed>}
     */
    private function buildConditionFromValue(string $condition, mixed $parameters, array $args): array
    {
        if ($parameters === null) {
            return ["$condition IS NULL", []];
        }

        if (is_array($args[1])) {
            if (empty($args[1])) {
                return ['1 = 0', []];
            }
            return ["$condition IN " . $this->quote($args[1]), []];
        }

        if ($parameters instanceof Literal) {
            return ["{$condition} = {$parameters}", []];
        }

        return ["$condition = ?", [$parameters]];
    }

    /**
     * Add where appending with OR
     *
     * @param string|array<string, mixed> $condition  - possibly containing ? or :name (PDO syntax)
     * @param mixed  $parameters
     *
     * @return $this
     */
    public function whereOr(string|array $condition, mixed $parameters = [])
    {
        if (is_array($condition)) {
            foreach ($condition as $key => $val) {
                $this->whereOr($key, $val);
            }
            return $this;
        }

        return $this->where($condition, $parameters, 'OR');
    }

    /**
     * @return string
     */
    protected function getClauseJoin()
    {
        return implode(' ', $this->statements['JOIN']);
    }

    /**
     * @return string
     */
    protected function getClauseWhere(): string
    {
        $firstStatement = array_shift($this->statements['WHERE']);
        $query = " WHERE {$firstStatement[1]}";

        foreach ($this->statements['WHERE'] as $statement) {
            $query .= " {$statement[0]} {$statement[1]}";
        }

        array_unshift($this->statements['WHERE'], $firstStatement);

        return $query;
    }

    /**
     * Statement can contain more tables (e.g. "table1.table2:table3:")
     *
     * @param string       $clause
     * @param string|null  $statement
     * @param array<int, mixed> $parameters
     *
     * @return $this
     */
    private function addJoinStatements(string $clause, ?string $statement, array $parameters = []): self
    {
        if ($statement === null) {
            $this->joins = [];
            return $this->resetClause('JOIN');
        }

        if (in_array(substr($statement, 0, -1), $this->joins)) {
            return $this;
        }

        [$joinAlias, $joinTable] = $this->setJoinNameAlias($statement);

        $statementUpper = strtoupper($statement);
        if (str_contains($statementUpper, ' ON ') || str_contains($statementUpper, ' USING')) {
            return $this->addRawJoins($clause, $statement, $parameters, $joinAlias, $joinTable);
        }

        $mainTable = $this->setMainTable();
        $lastChar = substr($joinTable, -1);
        if ($lastChar !== '.' && $lastChar !== ':') {
            $joinTable .= '.';
        }

        $this->regex->tableJoin($joinTable, $matches);

        if (empty($matches[1])) {
            return $this;
        }

        $lastItem = array_pop($matches[1]);
        $matches[1][] = $lastItem;

        foreach ($matches[1] as $joinItem) {
            if ($this->matchTableWithJoin($mainTable, $joinItem)) {
                continue;
            }
            $mainTable = $this->applyTableJoin($clause, $parameters, $mainTable, $joinItem, $lastItem, $joinAlias);
        }

        return $this;
    }

    /**
     * Create join string
     *
     * @param string $clause
     * @param string $mainTable
     * @param string $joinTable
     * @param string $joinAlias
     *
     * @return string
     */
    private function createJoinStatement(string $clause, string $mainTable, string $joinTable, string $joinAlias = ''): string
    {
        $mainTableLast = substr($mainTable, -1);
        if ($mainTableLast === ':' || $mainTableLast === '.') {
            $mainTable = substr($mainTable, 0, -1);
        }

        $referenceDirection = substr($joinTable, -1);
        $joinTable = substr($joinTable, 0, -1);
        $asJoinAlias = $joinAlias !== '' ? " AS $joinAlias" : '';
        $joinAlias = $joinAlias !== '' ? $joinAlias : $joinTable;

        if (in_array($joinAlias, $this->joins)) {
            return '';
        }

        $this->joins[] = $joinAlias;

        if ($referenceDirection === ':') {
            $primaryKey = $this->getStructure()->getPrimaryKey($mainTable);
            $foreignKey = $this->getStructure()->getForeignKey($mainTable);
            $pkStr = is_array($primaryKey) ? implode(', ', $primaryKey) : $primaryKey;

            return " $clause $joinTable$asJoinAlias ON $joinAlias.$foreignKey = $mainTable.$pkStr";
        }

        $primaryKey = $this->getStructure()->getPrimaryKey($joinTable);
        $foreignKey = $this->getStructure()->getForeignKey($joinTable);
        $pkStr = is_array($primaryKey) ? implode(', ', $primaryKey) : $primaryKey;

        return " $clause $joinTable$asJoinAlias ON $joinAlias.$pkStr = $mainTable.$foreignKey";
    }

    /**
     * Create undefined joins from statement with column with referenced tables
     *
     * @param string|array{0: string, 1: string} $statement
     *
     * @return string|array{0: string, 1: string} - the rewritten $statement (e.g. tab1.tab2:col => tab2.col)
     */
    private function createUndefinedJoins(string|array $statement): string|array
    {
        if ($this->isEscapedJoin($statement)) {
            return $statement;
        }

        $separator = null;
        if (is_array($statement)) {
            $separator = $statement[0];
            $statement = $statement[1];
        }

        $this->regex->tableJoinFull($statement, $matches);

        foreach ($matches[1] as $join) {
            if (!in_array(substr($join, 0, -1), $this->joins)) {
                $this->addJoinStatements('LEFT JOIN', $join);
            }
        }

        foreach ($this->joins as $join) {
            if (str_contains($join, '.') && str_starts_with($statement, $join)) {
                return $separator !== null ? [$separator, $statement] : $statement;
            }
        }

        $statement = $this->regex->removeAdditionalJoins($statement);
        $statement = is_array($statement) ? implode('', $statement) : (string) $statement;

        return $separator !== null ? [$separator, $statement] : $statement;
    }

    /**
     * @throws Exception
     *
     * @return string
     */
    protected function buildQuery(): string
    {
        $statementsWithReferences = ['WHERE', 'SELECT', 'GROUP BY', 'ORDER BY'];

        foreach ($statementsWithReferences as $clause) {
            if (!array_key_exists($clause, $this->statements)) {
                continue;
            }
            $this->statements[$clause] = array_map([$this, 'createUndefinedJoins'], $this->statements[$clause]);
        }

        return parent::buildQuery();
    }

    /**
     * @param string|array{0: string, 1: string} $statement
     *
     * @return bool
     */
    protected function isEscapedJoin(string|array $statement): bool
    {
        $stmt = is_array($statement) ? $statement[1] : $statement;

        return !$this->isSmartJoinEnabled || str_contains($stmt, '\.') || str_contains($stmt, '\:');
    }

    /**
     * @param string $statement
     *
     * @return array{0: string, 1: string}
     */
    private function setJoinNameAlias(string $statement): array
    {
        $this->regex->tableAlias($statement, $matches);
        $joinAlias = '';
        $joinTable = $matches[1] ?? '';

        if ($matches && isset($matches[4]) && !in_array(strtoupper($matches[4]), ['ON', 'USING'])) {
            $joinAlias = $matches[4];
        }

        return [$joinAlias, $joinTable];
    }

    /**
     * @param string $table
     * @param string $joinItem
     *
     * @return bool
     */
    private function matchTableWithJoin(string $table, string $joinItem): bool
    {
        return $table == substr($joinItem, 0, -1);
    }

    /**
     * @param string $clause
     * @param string $statement
     * @param array<int, mixed> $parameters
     * @param string $joinAlias
     * @param string $joinTable
     *
     * @return $this
     */
    private function addRawJoins(string $clause, string $statement, array $parameters, string $joinAlias, string $joinTable): self
    {
        $joinAlias = $joinAlias !== '' ? $joinAlias : $joinTable;

        if (in_array($joinAlias, $this->joins)) {
            return $this;
        }

        $this->joins[] = $joinAlias;

        return $this->addStatement('JOIN', " $clause $statement", $parameters);
    }

    /**
     * @return string
     */
    private function setMainTable(): string
    {
        if (isset($this->statements['FROM'])) {
            return $this->statements['FROM'];
        }
        if (isset($this->statements['UPDATE'])) {
            return $this->statements['UPDATE'];
        }

        return '';
    }

    /**
     * @param string $clause
     * @param array<int, mixed> $parameters
     * @param string $mainTable
     * @param string $joinItem
     * @param string $lastItem
     * @param string $joinAlias
     *
     * @return string
     */
    private function applyTableJoin(string $clause, array $parameters, string $mainTable, string $joinItem, string $lastItem, string $joinAlias): string
    {
        $alias = $joinItem === $lastItem ? $joinAlias : '';
        $newJoin = $this->createJoinStatement($clause, $mainTable, $joinItem, $alias);

        if ($newJoin !== '') {
            $this->addStatement('JOIN', $newJoin, $parameters);
        }

        return $joinItem;
    }

    public function __clone(): void
    {
        parent::__clone();

        foreach ($this->clauses as $clause => $value) {
            if (!is_array($value) || !isset($value[0]) || !$value[0] instanceof Common) {
                continue;
            }
            $this->clauses[$clause][0] = $this;
        }
    }
}
