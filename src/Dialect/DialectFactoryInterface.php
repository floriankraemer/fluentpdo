<?php

declare(strict_types=1);

namespace Envms\FluentPDO\Dialect;

use PDO;

interface DialectFactoryInterface
{
    public static function create(PDO $pdo): DialectInterface;
}
