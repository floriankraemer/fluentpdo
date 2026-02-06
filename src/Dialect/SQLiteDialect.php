<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Dialect;

class SQLiteDialect extends AbstractDialect
{
    public function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            return implode('.', array_map(
                fn($part) => '"' . str_replace('"', '""', trim($part)) . '"',
                explode('.', $identifier)
            ));
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function formatLimit(?int $limit, ?int $offset): string
    {
        if ($limit === null) {
            return '';
        }

        $clause = " LIMIT $limit";
        if ($offset !== null && $offset > 0) {
            $clause .= " OFFSET $offset";
        }

        return $clause;
    }

    public function getLastInsertIdQuery(?string $sequence = null): string
    {
        return 'last_insert_rowid()';
    }

    public function supportsUpsert(): bool
    {
        return true;
    }

    public function formatUpsert(array $updates): string
    {
        $sets = [];
        foreach ($updates as $key => $value) {
            $sets[] = "{$this->quoteIdentifier($key)} = excluded.{$this->quoteIdentifier($key)}";
        }

        return " ON CONFLICT DO UPDATE SET " . implode(', ', $sets);
    }

    public function getName(): string
    {
        return 'sqlite';
    }

    public function supportsFeature(string $feature): bool
    {
        return match($feature) {
            'upsert' => true,
            'json' => version_compare(SQLite3::version()['versionString'], '3.38.0', '>='),
            default => false
        };
    }
}