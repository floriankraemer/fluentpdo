<?php

declare(strict_types=1);

namespace Envms\FluentPDO;

/**
 * Class Structure
 */
class Structure
{

    /** @var string|array<int, string> */
    private $primaryKey;
    /** @var string|array<int, string>|callable */
    private $foreignKey;

    /** @var array<string, array<int, string>> */
    private array $primaryKeys = [];

    /**
     * Structure constructor
     *
     * @param string|array<int, string>       $primaryKey
     * @param string|array<int, string>|callable|null $foreignKey
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
     *
     * @return string|array<int, string>
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
     *
     * @param array<int, string> $columns
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
     * @param string|array<int, string>|callable $key
     * @param string                            $table
     *
     * @return string
     */
    private function key(string|array|callable $key, string $table): string
    {
        if (is_callable($key)) {
            return $key($table);
        }

        if (is_array($key)) {
            return implode(', ', $key);
        }

        return sprintf($key, $table);
    }

}
