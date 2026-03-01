<?php

declare(strict_types=1);

use Envms\FluentPDO\Dialect\MySQLDialect;

// Use environment variables for configuration
$driver = getenv('DB_DRIVER') ?: 'mysql';
$host = getenv('DB_HOST') ?: 'mysql'; // Use 'mysql' service name in Docker
$dbname = getenv('DB_NAME') ?: 'fluentdb';
$user = getenv('DB_USER') ?: 'vagrant'; // Use vagrant user created by Docker
$pass = getenv('DB_PASS') ?: 'vagrant'; // Use vagrant password

$dsn = match($driver) {
    'mysql' => "mysql:dbname=$dbname;host=" . ($host === 'localhost' ? 'mysql' : $host) . ";charset=utf8mb4",
    'sqlite' => "sqlite::memory:",
    'pgsql' => "pgsql:host=$host;dbname=$dbname",
    default => throw new Exception("Unsupported driver: $driver")
};

$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Load test schema based on driver
if ($driver === 'mysql') {
    $pdo->exec(file_get_contents(__DIR__ . '/fluentdb.sql'));
} elseif ($driver === 'pgsql') {
    $pdo->exec(file_get_contents(__DIR__ . '/fluentdb_pgsql.sql'));
} elseif ($driver === 'sqlite') {
    $pdo->exec(file_get_contents(__DIR__ . '/fluentdb_sqlite.sql'));
}

// Make $pdo available globally for tests
$GLOBALS['pdo'] = $pdo;