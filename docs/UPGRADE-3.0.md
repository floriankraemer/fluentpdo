# Upgrading from FluentPDO 2.x to 3.0

## Requirements

- PHP 8.3 or higher (was: PHP 7.1+)
- PHPUnit 11.0 or higher for testing (was: PHPUnit 8.0)

## Breaking Changes

### 1. Strict Types

All files now use `declare(strict_types=1)`. Type mismatches will throw `TypeError`.

**Before:**
```php
$query->where('id', '1'); // String accepted
```

**After:**

```php
$query->where('id', 1); // Must be int if column is int type
```

### 2. Result Handling

`execute()` now returns `Result` wrapper instead of `PDOStatement` for SELECT queries.

**Before:**

```php
$stmt = $query->execute();
$row = $stmt->fetch();
```

**After:**

```php
$result = $query->execute();
$row = $result->fetch(); // Same interface, but memory-safe
```

### 3. Composite Primary Keys

Delete operations now support composite primary keys.

**Before:**

```php
// Would fail on tables with composite keys
$fluent->delete('table', 1);
```

**After:**

```php
// Single key (unchanged)
$fluent->delete('table', 1);

// Composite key (new)
$fluent->delete('table', ['user_id' => 1, 'project_id' => 2]);
```

### 4. Empty Arrays in WHERE

Empty arrays now correctly produce FALSE condition instead of being ignored.

**Before:**

```php
$ids = [];
$query->where('id', $ids); // Condition ignored, returns all rows
```

**After:**

```php
$ids = [];
$query->where('id', $ids); // Returns 0 rows (1 = 0 condition)
```

### 5. Query State Cleanup

Query objects now clear internal state after execution to save memory.

**Before:**

```php
$query = $fluent->from('user')->where('id', 1);
$query->execute();
// Internal arrays still retained
```

**After:**

```php
$query = $fluent->from('user')->where('id', 1);
$query->execute();
// Internal arrays cleared, memory freed
```

## New Features

### 1. Multi-Dialect Support

FluentPDO now supports MySQL, PostgreSQL, SQLite, and SQL Server.

```php
// Automatically detects dialect from PDO connection
$pdo = new PDO('pgsql:host=localhost;dbname=test');
$fluent = new Query($pdo); // Uses PostgreSQL dialect

// Or specify manually
use Envms\FluentPDO\Dialect\PostgreSQLDialect;
$fluent = new Query($pdo, null, new PostgreSQLDialect($pdo));
```

### 2. Memory-Efficient Chunking

Process large result sets without loading everything into memory.

```php
$fluent->from('large_table')
    ->chunk(1000, function($rows) {
        foreach ($rows as $row) {
            // Process row
        }
    });
```

### 3. Better Cloning

Query cloning now properly deep-clones all internal state.

```php
$baseQuery = $fluent->from('user');
$query1 = clone $baseQuery; // Fully independent copy
$query2 = clone $baseQuery; // Another independent copy
```

### 4. ODBC Support

Works with databases using PDO_ODBC that don't support `PDO::quote()`.

```php
$pdo = new PDO('odbc:Driver={SQL Server};Server=localhost;Database=test');
$fluent = new Query($pdo); // Works correctly
```

## Recommendations

1. **Update tests to PHPUnit 11** - Use attributes instead of doc comments
2. **Enable strict types** - Add `declare(strict_types=1)` to your code
3. **Use chunking for large queries** - Prevents memory exhaustion
4. **Test with your database** - Verify dialect-specific behavior
5. **Review composite key usage** - Update delete operations if needed