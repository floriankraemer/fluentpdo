<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Dialect;

interface DialectInterface
{
    /**
     * Quote identifier (table/column name)
     */
    public function quoteIdentifier(string $identifier): string;

    /**
     * Quote string value (alternative to PDO::quote for unsupported drivers)
     */
    public function quoteValue(mixed $value): string;

    /**
     * Format LIMIT clause
     */
    public function formatLimit(?int $limit, ?int $offset): string;

    /**
     * Get name of last inserted ID
     */
    public function getLastInsertIdQuery(?string $sequence = null): string;

    /**
     * Support for INSERT ... ON DUPLICATE KEY UPDATE (MySQL)
     * or INSERT ... ON CONFLICT (PostgreSQL)
     */
    public function supportsUpsert(): bool;

    /**
     * Format UPSERT clause
     */
    public function formatUpsert(array $updates): string;

    /**
     * Get driver name
     */
    public function getName(): string;

    /**
     * Check if driver supports feature
     */
    public function supportsFeature(string $feature): bool;
}