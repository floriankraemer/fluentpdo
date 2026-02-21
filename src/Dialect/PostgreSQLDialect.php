<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Dialect;

class PostgreSQLDialect extends AbstractDialect
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
        if ($limit === null && $offset === null) {
            return '';
        }

        $clause = '';
        if ($limit !== null) {
            $clause .= " LIMIT $limit";
        }
        if ($offset !== null && $offset > 0) {
            $clause .= " OFFSET $offset";
        }

        return $clause;
    }

    public function getLastInsertIdQuery(?string $sequence = null): string
    {
        if ($sequence !== null) {
            return "currval('$sequence')";
        }
        return 'LASTVAL()';
    }

    public function supportsUpsert(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $updates
     */
    public function formatUpsert(array $updates): string
    {
        $sets = [];
        foreach ($updates as $key => $value) {
            $sets[] = "{$this->quoteIdentifier($key)} = EXCLUDED.{$this->quoteIdentifier($key)}";
        }

        return " ON CONFLICT DO UPDATE SET " . implode(', ', $sets);
    }

    public function getName(): string
    {
        return 'pgsql';
    }

    public function supportsFeature(string $feature): bool
    {
        return match($feature) {
            'upsert', 'subquery_join', 'json', 'returning' => true,
            default => false
        };
    }
}