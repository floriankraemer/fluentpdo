<?php

declare(strict_types=1);

namespace Envms\FluentTest\Model;

/**
 * Test User model class for PHPUnit tests
 */
class User
{
    public int $id;
    public int $country_id;
    public string $type;
    public string $name;
}