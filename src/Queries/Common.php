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

        if ($clause == 'GROUP' || $clause == 'ORDER') {
            $clause = "{$clause} BY";
        }

        if ($clause == 'COMMENT') {
            $clause = "\n--";
        }

        $statement = array_shift($parameters);

        if (strpos($clause, 'JOIN') !== false) {
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

        if (is_array($condition)) { // where(["column1 > ?" => 1, "column2 < ?" => 2])
            foreach ($condition as $key => $val) {
                $this->where($key, $val);
            }

            return $this;
        }

        $args = func_get_args();

        if ($parameters === []) {
            return $this->addWhereStatement($condition, $separator);
        }

        /*
         * Check that there are 2 arguments, a condition and a parameter value. If the condition contains
         * a parameter (? or :name), add them; it's up to the dev to be valid sql. Otherwise it's probably
         * just an identifier, so construct a new condition based on the passed parameter value.
         */
        if (count($args) >= 2 && !$this->regex->sqlParameter($condition)) {
            // condition is column only
            if (is_null($parameters)) {
                return $this->addWhereStatement("$condition IS NULL", $separator);
            // ! CHANGE: Empty array should result in FALSE condition
            } elseif (is_array($args[1])) {
                if (empty($args[1])) {
                    // Empty IN clause - always false
                    return $this->addWhereStatement('1 = 0', $separator);
                }

                $in = $this->quote($args[1]);

                return $this->addWhereStatement("$condition IN $in", $separator);
            }

            // don't parameterize the value if it's an instance of Literal
            if ($parameters instanceof Literal) {
                $condition = "{$condition} = {$parameters}";

                return $this->addWhereStatement($condition, $separator);
            } else {
                $condition = "$condition = ?";
            }
        }

        $args = [0 => $args[1]];

        // parameters can be passed as [1, 2, 3] and it will fill a condition like: id IN (?, ?, ?)
        if (is_array($parameters)) {
            $args = $parameters;
        }

        return $this->addWhereStatement($condition, $separator, $args);
    }

    /**
     * Add where appending with OR
     *
     * @param string $condition  - possibly containing ? or :name (PDO syntax)
     * @param mixed  $parameters
     *
     * @return $this
     */
    public function whereOr($condition, $parameters = [])
    {
        if (is_array($condition)) { // where(["column1 > ?" => 1, "column2 < ?" => 2])
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
    protected function getClauseWhere() {
        $firstStatement = array_shift($this->statements['WHERE']);
        $query = " WHERE {$firstStatement[1]}"; // append first statement to WHERE without condition

        if (!empty($this->statements['WHERE'])) {
            foreach ($this->statements['WHERE'] as $statement) {
                $query .= " {$statement[0]} {$statement[1]}"; // [0] -> AND/OR [1] -> field = ?
            }
        }

        // put the first statement back onto the beginning of the array in case we want to run this again
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
    private function addJoinStatements(string $clause, ?string $statement, array $parameters = [])
    {
        if ($statement === null) {
            $this->joins = [];

            return $this->resetClause('JOIN');
        }

        if (array_search(substr($statement, 0, -1), $this->joins) !== false) {
            return $this;
        }

        list($joinAlias, $joinTable) = $this->setJoinNameAlias($statement);

        if (strpos(strtoupper($statement), ' ON ') !== false || strpos(strtoupper($statement), ' USING') !== false) {
            return $this->addRawJoins($clause, $statement, $parameters, $joinAlias, $joinTable);
        }

        $mainTable = $this->setMainTable();

        // if $joinTable does not end with a dot or colon, append one
        if (!in_array(substr($joinTable, -1), ['.', ':'])) {
            $joinTable .= '.';
        }

        $this->regex->tableJoin($joinTable, $matches);

        // used for applying the table alias
        if (empty($matches[1])) {
            return $this;
        }
        $lastItem = array_pop($matches[1]);
        assert($lastItem !== false);
        array_push($matches[1], $lastItem);

        foreach ($matches[1] as $joinItem) {
            if ($this->matchTableWithJoin($mainTable, $joinItem)) {
                // this is still the same table so we don't need to add the same join
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
    private function createJoinStatement(string $clause, string $mainTable, string $joinTable, string $joinAlias = '')
    {
        if (in_array(substr($mainTable, -1), [':', '.'])) {
            $mainTable = substr($mainTable, 0, -1);
        }

        $referenceDirection = substr($joinTable, -1);
        $joinTable = substr($joinTable, 0, -1);
        $asJoinAlias = '';

        if (!empty($joinAlias)) {
            $asJoinAlias = " AS $joinAlias";
        } else {
            $joinAlias = $joinTable;
        }

        if (in_array($joinAlias, $this->joins)) { // if the join exists don't create it again
            return '';
        } else {
            $this->joins[] = $joinAlias;
        }

        if ($referenceDirection == ':') { // back reference
            $primaryKey = $this->getStructure()->getPrimaryKey($mainTable);
            $foreignKey = $this->getStructure()->getForeignKey($mainTable);
            $pkStr = is_array($primaryKey) ? implode(', ', $primaryKey) : $primaryKey;

            return " $clause $joinTable$asJoinAlias ON $joinAlias.$foreignKey = $mainTable.$pkStr";
        } else {
            $primaryKey = $this->getStructure()->getPrimaryKey($joinTable);
            $foreignKey = $this->getStructure()->getForeignKey($joinTable);
            $pkStr = is_array($primaryKey) ? implode(', ', $primaryKey) : $primaryKey;

            return " $clause $joinTable$asJoinAlias ON $joinAlias.$pkStr = $mainTable.$foreignKey";
        }
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
        // if we're in here, this is a where clause
        if (is_array($statement)) {
            $separator = $statement[0];
            $statement = $statement[1];
        }

        assert(is_string($statement));

        // matches a table name made of any printable characters followed by a dot/colon,
        // followed by any letters, numbers and most punctuation (to exclude '*')
        $this->regex->tableJoinFull($statement, $matches);

        foreach ($matches[1] as $join) {
            // remove the trailing dot and compare with the joins we already have
            if (!in_array(substr($join, 0, -1), $this->joins)) {
                $this->addJoinStatements('LEFT JOIN', $join);
            }
        }

        // don't rewrite table from other databases
        foreach ($this->joins as $join) {
            if (strpos($join, '.') !== false && strpos($statement, $join) === 0) {
                // rebuild the where statement
                if ($separator !== null) {
                    return [$separator, $statement];
                }
                return $statement;
            }
        }

        $statement = $this->regex->removeAdditionalJoins($statement);
        $statement = is_array($statement) ? implode('', $statement) : (string) $statement;

        // rebuild the where statement
        if ($separator !== null) {
            return [$separator, $statement];
        }

        return $statement;
    }

    /**
     * @throws Exception
     *
     * @return string
     */
    protected function buildQuery(): string
    {
        // first create extra join from statements with columns with referenced tables
        $statementsWithReferences = ['WHERE', 'SELECT', 'GROUP BY', 'ORDER BY'];

        foreach ($statementsWithReferences as $clause) {
            if (array_key_exists($clause, $this->statements)) {
                $this->statements[$clause] = array_map([$this, 'createUndefinedJoins'], $this->statements[$clause]);
            }
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
        if (is_array($statement)) {
            $statement = $statement[1];
        }

        return !$this->isSmartJoinEnabled || strpos($statement, '\.') !== false || strpos($statement, '\:') !== false;
    }

    /**
     * @param string $statement
     *
     * @return array{0: string, 1: string}
     */
    private function setJoinNameAlias(string $statement): array
    {
        $this->regex->tableAlias($statement, $matches); // store any found alias in $matches
        $joinAlias = '';
        $joinTable = '';

        if ($matches) {
            $joinTable = $matches[1];
            if (isset($matches[4]) && !in_array(strtoupper($matches[4]), ['ON', 'USING'])) {
                $joinAlias = $matches[4];
            }
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
    private function addRawJoins(string $clause, string $statement, array $parameters, string $joinAlias, string $joinTable)
    {
        if (!$joinAlias) {
            $joinAlias = $joinTable;
        }

        if (in_array($joinAlias, $this->joins)) {
            return $this;
        } else {
            $this->joins[] = $joinAlias;
            $statement = " $clause $statement";

            return $this->addStatement('JOIN', $statement, $parameters);
        }
    }

    /**
     * @return string
     */
    private function setMainTable()
    {
        if (isset($this->statements['FROM'])) {
            return $this->statements['FROM'];
        } elseif (isset($this->statements['UPDATE'])) {
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
        $alias = '';

        if ($joinItem == $lastItem) {
            $alias = $joinAlias; // use $joinAlias only for $lastItem
        }

        $newJoin = $this->createJoinStatement($clause, $mainTable, $joinItem, $alias);

        if ($newJoin) {
            $this->addStatement('JOIN', $newJoin, $parameters);
        }

        return $joinItem;
    }

    public function __clone(): void
    {
        // First call parent __clone
        parent::__clone();

        // Fix circular references in clauses
        foreach ($this->clauses as $clause => $value) {
            if (is_array($value) && isset($value[0]) && $value[0] instanceof Common) {
                $this->clauses[$clause][0] = $this;
            }
        }
    }
}
