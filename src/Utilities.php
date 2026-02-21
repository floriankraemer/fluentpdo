<?php

declare(strict_types=1);

namespace Envms\FluentPDO;

use Envms\FluentPDO\Queries\Result;

/**
 * Class Utilities
 */
class Utilities
{
    /**
     * Convert "camelCaseWord" to "CAMEL CASE WORD"
     *
     * @param string $string
     *
     * @return string
     */
    public static function toUpperWords(string $string): string
    {
        $regex = new Regex();
        $spaced = $regex->camelCaseSpaced($string);
        $result = is_array($spaced) ? implode('', $spaced) : (string) ($spaced ?? '');
        return trim(strtoupper($result));
    }

    /**
     * @param string|\Stringable $query - SQL string or query object with __toString
     *
     * @return string
     */
    public static function formatQuery(string|\Stringable $query): string
    {
        $regex = new Regex();
        $queryStr = is_string($query) ? $query : $query->__toString();

        $queryStr = $regex->splitClauses($queryStr);
        $queryStr = is_array($queryStr) ? implode('', $queryStr) : (string) ($queryStr ?? '');
        $queryStr = $regex->splitSubClauses($queryStr);
        $queryStr = is_array($queryStr) ? implode('', $queryStr) : (string) ($queryStr ?? '');
        $queryStr = $regex->removeLineEndWhitespace($queryStr);
        return is_array($queryStr) ? implode('', $queryStr) : (string) ($queryStr ?? '');
    }

    /**
     * Converts columns from strings to types according to PDOStatement::columnMeta()
     *
     * @param \PDOStatement|Result $statement
     * @param array<string, mixed>|array<int, array<string, mixed>>|\Traversable<int, array<string, mixed>> $rows - provided by PDOStatement::fetch with PDO::FETCH_ASSOC
     *
     * @return array<string, mixed>|array<int, array<string, mixed>>|\Traversable<int, array<string, mixed>>
     */
    public static function stringToNumeric(\PDOStatement|Result $statement, array|\Traversable $rows): array|\Traversable
    {
        // Get the underlying PDOStatement from Result if needed
        $pdoStatement = $statement instanceof Result ? $statement->getStatement() : $statement;

        for ($i = 0; ($columnMeta = $pdoStatement->getColumnMeta($i)) !== false; $i++) {
            $type = $columnMeta['native_type'] ?? 'STRING';

            switch ($type) {
                case 'DECIMAL':
                case 'DOUBLE':
                case 'FLOAT':
                case 'INT24':
                case 'LONG':
                case 'LONGLONG':
                case 'NEWDECIMAL':
                case 'SHORT':
                case 'TINY':
                    $colName = $columnMeta['name'];
                    if (is_array($rows) && !isset($rows[0]) && isset($rows[$colName])) {
                        $rows[$colName] = $rows[$colName] + 0;
                    } elseif ($rows instanceof \Traversable) {
                        foreach ($rows as &$row) {
                            /** @var array<string, mixed> $row */
                            if (isset($row[$colName])) {
                                $row[$colName] = $row[$colName] + 0;
                            }
                        }
                        unset($row);
                    }
                    break;
                default:
                    // return as string
                    break;
            }
        }

        return $rows;
    }

    /**
     * @param array<int|string, mixed>|mixed $value
     *
     * @return array<int|string, mixed>|int|string|bool|float|null
     */
    public static function convertSqlWriteValues(mixed $value): array|int|string|bool|float|null
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::convertValue($v);
            }
            return $value;
        }
        return self::convertValue($value);
    }

    /**
     * @param mixed $value
     *
     * @return int|string
     */
    public static function convertValue(mixed $value)
    {
        switch (gettype($value)) {
            case 'boolean':
                $conversion = ($value) ? 1 : 0;
                break;
            default:
                $conversion = $value;
                break;
        }

        return $conversion;
    }

    /**
     * @param mixed $subject
     *
     * @return bool
     */
    public static function isCountable(mixed $subject)
    {
        return (is_array($subject) || ($subject instanceof \Countable));
    }

    /**
     * @param mixed $value
     *
     * @return Literal|mixed
     */
    public static function nullToLiteral(mixed $value)
    {
        if ($value === null) {
            return new Literal('NULL');
        }

        return $value;
    }
}
