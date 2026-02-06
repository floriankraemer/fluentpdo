<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Dialect;

class SQLServerDialect extends AbstractDialect
{
    public function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            return implode('.', array_map(
                fn($part) => '[' . str_replace(']', ']]', trim($part)) . ']',
                explode('.', $identifier)
            ));
        }

        return '[' . str_replace(']', ']]', $identifier) . ']';
    }

    public function formatLimit(?int $limit, ?int $offset): string
    {
        // SQL Server uses OFFSET...FETCH instead of LIMIT
        if ($limit === null && $offset === null) {
            return '';
        }

        $offset = $offset ?? 0;
        $clause = " OFFSET $offset ROWS";

        if ($limit !== null) {
            $clause .= " FETCH NEXT $limit ROWS ONLY";
        }

        return $clause;
    }

    public function getLastInsertIdQuery(?string $sequence = null): string
    {
        return 'SCOPE_IDENTITY()';
    }

    public function getName(): string
    {
        return 'sqlsrv';
    }

    public function supportsFeature(string $feature): bool
    {
        return match($feature) {
            'json' => true, // SQL Server 2016+
            default => false
        };
    }

    protected function manualEscape(string $value): string
    {
        // SQL Server specific escaping
        return str_replace("'", "''", $value);
    }
}