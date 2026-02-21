<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Queries;

use Envms\FluentPDO\{Exception, Query};

/**
 * DELETE query builder
 *
 * @method Delete  leftJoin(string $statement) add LEFT JOIN to query
 *                        ($statement can be 'table' name only or 'table:' means back reference)
 * @method Delete  innerJoin(string $statement) add INNER JOIN to query
 *                        ($statement can be 'table' name only or 'table:' means back reference)
 * @method Delete  from(string $table) add LIMIT to query
 * @method Delete  orderBy(string $column) add ORDER BY to query
 * @method Delete  limit(int $limit) add LIMIT to query
 */
class Delete extends Common
{

    private bool $ignore = false;

    /**
     * ! CHANGE: Support composite primary keys
     *
     * @param array<int|string, mixed>|null $primaryKey
     */
    public function __construct(Query $fluent, string $table, int|array|null $primaryKey = null)
    {
        $clauses = [
            'DELETE FROM' => [$this, 'getClauseDeleteFrom'],
            'DELETE'      => [$this, 'getClauseDelete'],
            'FROM'        => null,
            'JOIN'        => [$this, 'getClauseJoin'],
            'WHERE'       => [$this, 'getClauseWhere'],
            'ORDER BY'    => ', ',
            'LIMIT'       => null,
        ];

        parent::__construct($fluent, $clauses);

        $this->statements['DELETE FROM'] = $table;
        $this->statements['DELETE'] = $table;

        if ($primaryKey === null) {
            return;
        }

        $pkColumns = $fluent->getStructure()->getPrimaryKey($table);

        if (!is_array($pkColumns)) {
            $this->where($pkColumns, $primaryKey);
            return;
        }

        if (!is_array($primaryKey)) {
            throw new Exception("Table '$table' has composite primary key, array of values required");
        }

        foreach ($pkColumns as $column) {
            if (!isset($primaryKey[$column])) {
                throw new Exception("Missing value for primary key column '$column'");
            }
            $this->where($column, $primaryKey[$column]);
        }
    }

    /**
     * Forces delete operation to fail silently
     *
     * @return Delete
     */
    public function ignore()
    {
        $this->ignore = true;

        return $this;
    }

    /**
     * @throws Exception
     *
     * @return string
     */
    protected function buildQuery(): string
    {
        if ($this->statements['FROM']) {
            unset($this->clauses['DELETE FROM']);
        } else {
            unset($this->clauses['DELETE']);
        }

        return parent::buildQuery();
    }

    /**
     * Execute DELETE query
     *
     * @throws Exception
     *
     * @return int|false
     */
    public function execute(mixed $param = null): int|false
    {
        if (empty($this->statements['WHERE'])) {
            throw new Exception('Delete queries must contain a WHERE clause to prevent unwanted data loss');
        }

        $result = parent::execute();

        if (!$result instanceof Result && !$result instanceof \PDOStatement) {
            return false;
        }

        return $result->rowCount();
    }

    /**
     * @return string
     */
    protected function getClauseDelete()
    {
        return 'DELETE' . ($this->ignore ? " IGNORE" : '') . ' ' . $this->statements['DELETE'];
    }

    /**
     * @return string
     */
    protected function getClauseDeleteFrom()
    {
        return 'DELETE' . ($this->ignore ? " IGNORE" : '') . ' FROM ' . $this->statements['DELETE FROM'];
    }

}
