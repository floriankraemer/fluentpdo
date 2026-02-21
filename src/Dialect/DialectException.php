<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Dialect;

use Envms\FluentPDO\Exception;

class DialectException extends Exception
{
    public static function withUnsupportedDriver(string $driver): self
    {
        return new self(sprintf('Unsupported database driver: %s', $driver));
    }
}
