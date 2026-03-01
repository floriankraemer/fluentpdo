<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Dialect;

use PDO;

abstract class AbstractDialect implements DialectInterface
{
    public function __construct(
        protected readonly PDO $pdo
    ) {}

    public function quoteValue(mixed $value): string
    {
        // Fallback implementation that works without PDO::quote()
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Try PDO::quote first, fallback to manual escaping
        try {
            $quoted = $this->pdo->quote((string) $value);
            if ($quoted === false) {
                // PDO::quote returns false for unsupported drivers (e.g., PDO_ODBC)
                return "'" . $this->manualEscape((string) $value) . "'";
            }
            return $quoted;
        } catch (\PDOException $e) {
            // PDO::quote not supported (e.g., PDO_ODBC)
            return "'" . $this->manualEscape((string) $value) . "'";
        }
    }

    protected function manualEscape(string $value): string
    {
        // Basic SQL escaping (dialect can override)
        return str_replace(
            ["'", "\\"],
            ["''", "\\\\"],
            $value
        );
    }

    public function supportsUpsert(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $updates
     */
    public function formatUpsert(array $updates): string
    {
        throw new \RuntimeException(
            "Upsert not supported by {$this->getName()}"
        );
    }

    public function supportsFeature(string $feature): bool
    {
        return false;
    }
}
