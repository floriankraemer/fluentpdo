<?php

declare(strict_types=1);

namespace Envms\FluentPDO;

/**
 * Class Structure
 */
class Structure
{

    /** @var string|array */
    private $primaryKey;
    /** @var string */
    private $foreignKey;

    /** @var array */
    private array $primaryKeys = [];

    /**
     * Structure constructor
     */
    public function __construct(
        string|array $primaryKey = 'id',
        string|array|callable|null $foreignKey = '%s_id'
    ) {
        $this->primaryKey = $primaryKey;
        $this->foreignKey = $foreignKey ?? (is_string($primaryKey) ? $primaryKey : 'id');
    }

    /**
     * ! CHANGE: Support array return for composite keys
     */
    public function getPrimaryKey(string $table): string|array
    {
        // Check if custom primary keys are defined
        if (isset($this->primaryKeys[$table])) {
            return $this->primaryKeys[$table];
        }

        // Return the configured primary key pattern
        if (is_array($this->primaryKey)) {
            return $this->primaryKey;
        }

        return $this->key($this->primaryKey, $table);
    }

    /**
     * ! NEW: Set composite primary key
     */
    public function setCompositePrimaryKey(string $table, array $columns): self
    {
        $this->primaryKeys[$table] = $columns;
        return $this;
    }

    /**
     * ! NEW: Check if table has composite key
     */
    public function hasCompositePrimaryKey(string $table): bool
    {
        $pk = $this->getPrimaryKey($table);
        return is_array($pk);
    }

    /**
     * @param string $table
     *
     * @return string
     */
    public function getForeignKey($table)
    {
        return $this->key($this->foreignKey, $table);
    }

    /**
     * @param string|callback $key
     * @param string          $table
     *
     * @return string
     */
    private function key($key, $table)
    {
        if (is_callable($key)) {
            return $key($table);
        }

        return sprintf($key, $table);
    }

}
