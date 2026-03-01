<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Queries;

use Envms\FluentPDO\{Exception, Literal, Query};

/**
 * REPLACE query builder (MySQL) / INSERT OR REPLACE (SQLite)
 */
class Replace extends Insert
{

    /**
     * ReplaceQuery constructor.
     *
     * @param Query $fluent
     * @param string $table
     * @param array<int|string, mixed> $values
     *
     * @throws Exception
     */
    public function __construct(Query $fluent, string $table, array $values)
    {
        parent::__construct($fluent, $table, $values);

        // Validate dialect support
        if (!$this->dialect->supportsUpsert()) {
            throw new Exception('REPLACE INTO / INSERT OR REPLACE is not supported by this database dialect');
        }
    }

    // Override parent properties that are accessed in getClauseInsertInto
    /** @var bool */
    private $ignore = false;
    /** @var bool */
    private $delayed = false;

    /**
     * @return string
     */
    protected function getClauseInsertInto()
    {
        $replaceKeyword = match($this->dialect->getName()) {
            'sqlite' => 'INSERT OR REPLACE',
            'mysql' => 'REPLACE',
            default => throw new Exception('REPLACE INTO / INSERT OR REPLACE is not supported by this database dialect')
        };

        return $replaceKeyword . ($this->ignore ? " IGNORE" : '') . ($this->delayed ? " DELAYED" : '') . ' INTO ' . $this->statements['INSERT INTO'];
    }

}