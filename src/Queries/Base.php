<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Queries;

use DateTime, IteratorAggregate, PDO, PDOStatement;
use Envms\FluentPDO\{Exception, Literal, Query, Regex, Structure, Utilities};
use Envms\FluentPDO\Dialect\DialectInterface;

/**
 * Base query builder
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
abstract class Base implements IteratorAggregate
{

    /** @var float */
    private $totalTime;

    /** @var float */
    private $executionTime;

    /** @var bool|object|string - false=array, true=stdClass, string=class name */
    private $object = false;

    /** @var Query */
    protected $fluent;

    /** @var PDOStatement|null|false */
    protected $result;

    /** @var array<string, mixed> - definition clauses */
    protected $clauses = [];
    /** @var array<string, mixed> */
    protected $statements = [];
    /** @var array<string, mixed>|array<int|string, mixed> */
    protected $parameters = [];

    /** @var DialectInterface */
    protected readonly DialectInterface $dialect;

    /** @var Regex */
    protected $regex;

    /** @var array<int|string, mixed>|null */
    private ?array $builtParameters = null;

    /** @var string|null */
    private ?string $builtQuery = null;

    /** @var string */
    protected $message = '';

    /** @var int */
    protected $currentFetchMode;

    /** @var array<int, string> - used by Common for JOIN tracking, empty in Base */
    protected $joins = [];

    /** @var bool - tracks if query was executed (for __clone reset) */
    private bool $executed = false;

    /**
     * BaseQuery constructor.
     *
     * @param Query $fluent
     * @param array<string, mixed> $clauses
     */
    protected function __construct(Query $fluent, array $clauses)
    {
        $this->currentFetchMode = defined('PDO::FETCH_DEFAULT') ? PDO::FETCH_DEFAULT : PDO::FETCH_BOTH;
        $this->fluent = $fluent;
        $this->clauses = $clauses;
        $this->result = null;
        $this->dialect = $fluent->getDialect();

        $this->initClauses();

        $this->regex = new Regex();
    }

    /**
     * Return formatted query when request class representation
     * ie: echo $query
     *
     * @return string - formatted query
     *
     * @throws Exception
     */
    public function __toString()
    {
        return $this->getQuery();
    }

    /**
     * Initialize statement and parameter clauses.
     */
    private function initClauses(): void
    {
        foreach ($this->clauses as $clause => $value) {
            if ($value) {
                $this->statements[$clause] = [];
                $this->parameters[$clause] = [];
            } else {
                $this->statements[$clause] = null;
                $this->parameters[$clause] = null;
            }
        }
    }

    /**
     * Clear query state after execution to free memory
     * ! IMPORTANT: Call this in execute() method
     */
    protected function clearState(): void
    {
        $this->statements = [];
        $this->parameters = [];
        $this->joins = [];
        $this->executed = true;
        // Keep $builtParameters and $builtQuery for getQuery()/getParameters() after execution
    }

    /**
     * Check if query has been executed
     */
    protected function isExecuted(): bool
    {
        return $this->executed;
    }

    /**
     * Add statement for all clauses except WHERE
     *
     * @param string $clause
     * @param string|array<int, string>|int|null $statement
     * @param array<int, mixed> $parameters
     *
     * @return $this
     */
    protected function addStatement(string $clause, string|array|int|null $statement, array $parameters = []): self
    {
        if ($statement === null) {
            return $this->resetClause($clause);
        }

        if ($this->clauses[$clause]) {
            // NEW (memory efficient): Use array_push instead of array_merge
            if (is_array($statement)) {
                array_push($this->statements[$clause], ...$statement);
            } else {
                $this->statements[$clause][] = $statement;
            }

            // Same for parameters
            if (!empty($parameters)) {
                array_push($this->parameters[$clause], ...$parameters);
            }
        } else {
            $this->statements[$clause] = $statement;
            $this->parameters[$clause] = $parameters;
        }

        return $this;
    }

    /**
     * Add statement for all kind of clauses
     *
     * @param string|array<int, string> $statement
     * @param string $separator - should be AND or OR
     * @param array<int|string, mixed> $parameters - positional (? placeholders) or named (':name' => value)
     *
     * @return $this
     */
    protected function addWhereStatement(string|array|null $statement, string $separator = 'AND', array $parameters = []): self
    {
        if ($statement === null) {
            return $this->resetClause('WHERE');
        }

        if (is_array($statement)) {
            foreach ($statement as $s) {
                $this->statements['WHERE'][] = [$separator, $s];
            }
        } else {
            $this->statements['WHERE'][] = [$separator, $statement];
        }

        if (!empty($parameters)) {
            foreach ($parameters as $key => $value) {
                if (is_string($key) && strpos($key, ':') === 0) {
                    $this->parameters['WHERE'][$key] = $value;
                } else {
                    $this->parameters['WHERE'][] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Remove all prev defined statements
     *
     * @param string $clause
     *
     * @return $this
     */
    protected function resetClause(string $clause): self
    {
        $this->statements[$clause] = null;
        $this->parameters[$clause] = [];
        if (isset($this->clauses[$clause]) && $this->clauses[$clause]) {
            $this->statements[$clause] = [];
        }

        return $this;
    }

    /**
     * Implements method from IteratorAggregate
     *
     * @return \Traversable<int, array<string, mixed>|object>
     *
     * @throws Exception
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): \Traversable
    {
        $result = $this->execute();
        if ($result instanceof \Traversable) {
            /** @var \Traversable<int, array<string, mixed>|object> $result */
            return $result;
        }
        return new \EmptyIterator();
    }

    /**
     * Execute query with earlier added parameters
     *
     * @return PDOStatement|Result|bool|int|string|null
     *
     * @throws Exception
     */
    public function execute(mixed $param = null): PDOStatement|Result|bool|int|string|null
    {
        $startTime = microtime(true);

        $query = $this->buildQuery();
        $parameters = $this->buildParameters();

        $this->prepareQuery($query);

        if ($this->result instanceof PDOStatement) {
            $this->setObjectFetchMode($this->result);

            $execTime = microtime(true);

            $this->executeQuery($parameters, (float) $startTime, (float) $execTime);
            $this->debug();

            // Clear state after execution to free memory
            $this->clearState();

            // Return Result wrapper only for SELECT queries that return data
            // For INSERT/UPDATE/DELETE, return the PDOStatement as before
            if ($this->result instanceof PDOStatement && $this instanceof \Envms\FluentPDO\Queries\Select) {
                return new Result(
                    $this->result,
                    $this->currentFetchMode,
                    $this->fluent->convertRead
                );
            }
        }

        return $this->result;
    }

    /**
     * @return Structure
     */
    protected function getStructure(): Structure
    {
        return $this->fluent->getStructure();
    }

    /**
     * Get PDOStatement result
     *
     * @return PDOStatement|null|false
     */
    public function getResult(): PDOStatement|null|false
    {
        return $this->result;
    }

    /**
     * Get query parameters
     *
     * @return array<int|string, mixed>
     */
    public function getParameters(): array
    {
        if ($this->builtParameters !== null) {
            return $this->builtParameters;
        }

        return $this->buildParameters();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawClauses(): array
    {
        return $this->clauses;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawStatements(): array
    {
        return $this->statements;
    }

    /**
     * @return array<string, mixed>|array<int|string, mixed>
     */
    public function getRawParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Gets the total time of query building, preparation and execution
     *
     * @return float
     */
    public function getTotalTime(): float
    {
        return $this->totalTime;
    }

    /**
     * Gets the query execution time
     *
     * @return float
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get query string
     *
     * @param bool $formatted - Return formatted query
     *
     * @return string
     * @throws Exception
     */
    public function getQuery(bool $formatted = true): string
    {
        $query = $this->buildQuery();

        if ($formatted) {
            $query = Utilities::formatQuery($query);
        }

        return $query;
    }

    /**
     * Select an item as object
     *
     * @param object|bool|string $object  If set to true, items are returned as stdClass, otherwise a class
     *                                    name can be passed and a new instance of this class is returned.
     *                                    Can be set to false to return items as an associative array.
     *
     * @return $this
     */
    public function asObject(object|bool|string $object = true)
    {
        $this->object = $object;

        return $this;
    }

    /**
     * Converts php null values to Literal instances to be inserted into a database
     */
    protected function convertNullValues(): void
    {
        $filterList = ['VALUES' => true, 'ON DUPLICATE KEY UPDATE' => true, 'SET' => true];

        foreach ($this->statements as $clause => $statement) {
            if (isset($filterList[$clause])) {
                if (isset($statement[0])) {
                    for ($i = 0, $iMax = count($statement); $i < $iMax; $i++) {
                        foreach ($statement[$i] as $key => $value) {
                            $this->statements[$clause][$i][$key] = Utilities::nullToLiteral($value);
                        }
                    }
                } else {
                    foreach ($statement as $key => $value) {
                        $this->statements[$clause][$key] = Utilities::nullToLiteral($value);
                    }
                }
            }
        }
    }

    /**
     * Generate query
     *
     * @return string
     * @throws Exception
     */
    protected function buildQuery(): string
    {
        if ($this->builtQuery !== null) {
            return $this->builtQuery;
        }

        if ($this->fluent->convertWrite === true) {
            $this->convertNullValues();
        }

        $query = '';

        foreach ($this->clauses as $clause => $separator) {
            if ($this->clauseNotEmpty($clause)) {
                if (is_string($separator)) {
                    $query .= " {$clause} " . implode($separator, $this->statements[$clause]);
                } elseif ($separator === null) {
                    $query .= " {$clause} {$this->statements[$clause]}";
                } elseif (is_callable($separator)) {
                    $query .= $separator();
                } else {
                    throw new Exception("Clause '$clause' is incorrectly set to '$separator'.");
                }
            }
        }

        $this->builtQuery = trim(str_replace(['\.', '\:'], ['.', ':'], $query));
        return $this->builtQuery;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function buildParameters(): array
    {
        if ($this->builtParameters !== null) {
            return $this->builtParameters;
        }

        $parameters = [];
        foreach ($this->parameters as $clauses) {
            if ($this->fluent->convertWrite === true) {
                $clauses = Utilities::convertSqlWriteValues($clauses);
            }

            if (is_array($clauses)) {
                foreach ($clauses as $key => $value) {
                    if (is_string($key) && strpos($key, ':') === 0) { // these are named params e.g. (':name' => 'Mark')
                        $parameters += [$key => $value];
                    } else {
                        $parameters[] = $value;
                    }
                }
            } elseif ($clauses !== false && $clauses !== null) {
                $parameters[] = $clauses;
            }
        }

        $this->builtParameters = $parameters;
        return $parameters;
    }

    /**
     * @param $value
     *
     * @return string
     */
    protected function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return '(' . implode(', ', array_map([$this, 'quote'], $value)) . ')';
        }

        // Use dialect instead of direct PDO::quote()
        return $this->dialect->quoteValue($value);
    }

    /**
     * @param string $clause
     *
     * @return bool
     */
    private function clauseNotEmpty(string $clause): bool
    {
        if ((Utilities::isCountable($this->statements[$clause])) && $this->clauses[$clause]) {
            return (bool)count($this->statements[$clause]);
        }

        return (bool)$this->statements[$clause];
    }

    /**
     * @param string $query
     *
     * @throws Exception
     */
    private function prepareQuery($query): void
    {
        $this->result = $this->fluent->getPdo()->prepare($query);

        /*
         At this point, $result is a PDOStatement instance, or false.
         PDO::prepare() does not reliably return errors. Some database drivers
         do not support prepared statements, and PHP emulates them. Postgresql
         does support prepared statements, but PHP does not call Postgresql's
         prepare function until we call PDOStatement::execute() below.
         If PDO::prepare() was consistent, this is where we would check
         for prepare errors, such as invalid SQL.
        */

        if ($this->result === false) {
            $error = $this->fluent->getPdo()->errorInfo();
            $this->message = "SQLSTATE: {$error[0]} - Driver Code: {$error[1]} - Message: {$error[2]}";

            if ($this->fluent->exceptionOnError === true) {
                throw new Exception($this->message);
            }
        }
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @param float $startTime
     * @param float $execTime
     *
     * @throws Exception
     */
    private function executeQuery(array $parameters, float $startTime, float $execTime): void
    {
        if ($this->result instanceof PDOStatement && $this->result->execute($parameters) === true) {
            $this->executionTime = microtime(true) - $execTime;
            $this->totalTime = microtime(true) - $startTime;
        } elseif ($this->result instanceof PDOStatement) {
            $error = $this->result->errorInfo();
            $this->message = "SQLSTATE: {$error[0]} - Driver Code: {$error[1]} - Message: {$error[2]}";

            if ($this->fluent->exceptionOnError === true) {
                throw new Exception($this->message);
            }

            $this->result = false;
        }
    }

    /**
     * @param PDOStatement $result
     */
    private function setObjectFetchMode(PDOStatement $result): void
    {
        if ($this->object !== false) {
            if (is_string($this->object) && class_exists($this->object)) {
                $this->currentFetchMode = PDO::FETCH_CLASS;
                $result->setFetchMode($this->currentFetchMode, $this->object);
            } else {
                $this->currentFetchMode = PDO::FETCH_OBJ;
                $result->setFetchMode($this->currentFetchMode);
            }
        } elseif ($this->fluent->getPdo()->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE) === PDO::FETCH_BOTH) {
            $this->currentFetchMode = PDO::FETCH_ASSOC;
            $result->setFetchMode($this->currentFetchMode);
        }
    }

    /**
     * Echo/pass a debug string
     *
     * @throws Exception
     */
    private function debug(): void
    {
        if (!empty($this->fluent->debug)) {
            if (!is_callable($this->fluent->debug)) {
                $backtrace = '';
                $query = $this->getQuery();
                $parameters = $this->getParameters();
                $debug = '';

                if ($parameters) {
                    $debug = '# parameters: ' . implode(', ', array_map([$this, 'quote'], $parameters)) . "\n";
                }

                $debug .= $query;

                foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15) as $backtrace) {
                    if (isset($backtrace['file']) && !$this->regex->compareLocation($backtrace['file'])) {
                        // stop at the first file outside the FluentPDO source
                        break;
                    }
                }

                $time = sprintf('%0.3f', $this->totalTime * 1000) . 'ms';
                $rows = ($this->result instanceof PDOStatement) ? $this->result->rowCount() : 0;
                $file = $backtrace['file'] ?? 'unknown';
                $line = $backtrace['line'] ?? 0;
                $finalString = "# {$file}:{$line} ({$time}; rows = {$rows})\n{$debug}\n\n";

                // if STDERR is set, send there, otherwise just output the string
                if (defined('STDERR') && is_resource(STDERR)) {
                    fwrite(STDERR, $finalString);
                } else {
                    echo $finalString;
                }
            } else {
                $debug = $this->fluent->debug;
                $debug($this);
            }
        }
    }

    /**
     * Fix __clone to deep clone all arrays
     * ! CHANGE: Complete implementation
     */
    public function __clone(): void
    {
        // Deep clone all arrays
        $this->statements = array_map(
            fn($stmt) => is_array($stmt) ? [...$stmt] : $stmt,
            $this->statements
        );

        $this->parameters = array_map(
            fn($param) => is_array($param) ? [...$param] : $param,
            $this->parameters
        );

        $this->joins = [...$this->joins];

        // Reset execution state
        $this->result = null;
        $this->executed = false;
        $this->builtParameters = null;
        $this->builtQuery = null;
    }
}
