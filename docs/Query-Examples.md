# FluentPDO Query Examples

This guide provides practical examples for common database operations with FluentPDO: selecting, inserting, deleting, and joining data.

## Setup

```php
$pdo = new PDO('mysql:dbname=myapp;host=localhost', 'user', 'password');
$fluent = new \Envms\FluentPDO\Query($pdo);
```

---

## SELECT

### Basic select

```php
// Select all columns from a table
$query = $fluent->from('user');

// Select with WHERE clause
$query = $fluent->from('user')
    ->where('id > ?', 0)
    ->where('name = ?', 'Marek');

// Select by primary key (shortcut)
$user = $fluent->from('user', 1)->fetch();
```

### Selecting specific columns

```php
// Replace default SELECT with specific columns
$query = $fluent->from('user')
    ->select(null)  // Clear default "table.*"
    ->select('id, name, email');

// Select array of columns
$query = $fluent->from('user')
    ->select(null)
    ->select(['id', 'name']);

// Add columns to existing select (keeps table.*)
$query = $fluent->from('article')
    ->select('user.name');
```

### WHERE conditions

```php
// Column = value (auto-generates placeholder)
$query = $fluent->from('user')->where('type', 'author');

// Column IS NULL
$query = $fluent->from('user')->where('deleted_at', null);

// Column IN (array of values)
$query = $fluent->from('user')->where('id', [1, 2, 3]);

// Custom condition with placeholders
$query = $fluent->from('user')->where('created_at > ?', $date);

// Multiple placeholders
$query = $fluent->from('user')->where('id IN (?, ?, ?)', [1, 2, 3]);

// Named parameters
$query = $fluent->from('user')
    ->where('type = :type', [':type' => 'author'])
    ->where('id > :id', [':id' => 5]);

// Array of conditions (AND)
$query = $fluent->from('user')->where([
    'id' => 2,
    'type' => 'author'
]);

// OR conditions
$query = $fluent->from('comment')
    ->where('comment.id = ?', 1)
    ->whereOr('user.id = ?', 2);

// Reset WHERE clause
$query = $fluent->from('user')->where(null)->where('active = ?', 1);
```

### ORDER BY, GROUP BY, LIMIT, OFFSET

```php
$query = $fluent->from('article')
    ->where('published = ?', 1)
    ->orderBy('published_at DESC')
    ->limit(10)
    ->offset(20);

// GROUP BY with HAVING
$query = $fluent->from('user')
    ->select(null)
    ->select('type, count(id) AS type_count')
    ->where('id > ?', 1)
    ->groupBy('type')
    ->having('type_count > ?', 1)
    ->orderBy('name');
```

### Fetching results

```php
// Single row (associative array)
$row = $fluent->from('user', 1)->fetch();

// Single column from row
$name = $fluent->from('user', 1)->fetch('name');

// All rows as array
$rows = $fluent->from('user')->fetchAll();

// Indexed by column (e.g. by id)
$usersById = $fluent->from('user')->fetchAll('id', 'name, email');

// Key-value pairs
$pairs = $fluent->from('user')->fetchPairs('id', 'name');
// Result: ['1' => 'Marek', '2' => 'Robert', ...]

// Single column as array
$names = $fluent->from('user')->fetchColumnArray(1);  // column index 1

// Count rows (uses SQL COUNT, memory-efficient)
$count = $fluent->from('article')->where('published = ?', 1)->count();

// Iterate (memory-efficient, no fetchAll)
foreach ($fluent->from('article') as $row) {
    echo $row['title'] . "\n";
}
```

---

## JOIN

### Smart joins (automatic)

When tables use standard primary/foreign key naming, FluentPDO builds joins automatically:

```php
// Just reference the foreign table.column — join is created automatically
$query = $fluent->from('article')
    ->select('user.name');

// Generated SQL:
// SELECT article.*, user.name
// FROM article
// LEFT JOIN user ON user.id = article.user_id
```

### Explicit joins

```php
// LEFT JOIN (short form — uses primary/foreign keys)
$query = $fluent->from('article')
    ->leftJoin('user')
    ->select('user.name');

// Full JOIN with custom ON clause
$query = $fluent->from('article')
    ->leftJoin('user ON user.id = article.user_id')
    ->select('user.name');

// INNER JOIN
$query = $fluent->from('article')
    ->innerJoin('user')
    ->where('user.type = ?', 'author');

// RIGHT JOIN, FULL JOIN, OUTER JOIN
$query = $fluent->from('article')
    ->rightJoin('comment')
    ->outerJoin('category');
```

### Table references with dot notation

Use `table.column` to reference columns across joined tables. FluentPDO creates the necessary joins:

```php
// References article.user_id → auto-joins user
$query = $fluent->from('article')
    ->where('user.name = ?', 'Marek');

// References comment.article_id and article.user_id
$query = $fluent->from('comment')
    ->select('article.title')
    ->select('user.name')
    ->where('article.published_at > ?', $date);
```

### Table aliases

```php
$query = $fluent->from('user author')
    ->select('author.name');

// Or explicit AS
$query = $fluent->from('user AS author')
    ->select('country.name');  // country joined via author.country_id
```

### Disable smart joins

To prevent automatic joins (e.g. when using custom table references):

```php
$query = $fluent->from('article')
    ->disableSmartJoin()
    ->where('user\.name = ?', 'Marek');  // Escape to prevent auto-join
```

---

## INSERT

### Single row

```php
$values = [
    'user_id' => 1,
    'title'   => 'My Article',
    'content' => 'Article content...'
];

// Insert and get last inserted ID
$lastId = $fluent->insertInto('article')->values($values)->execute();

// Shorthand: pass table and values together
$lastId = $fluent->insertInto('article', $values)->execute();
```

### Multiple rows

```php
$rows = [
    ['user_id' => 1, 'title' => 'Article 1', 'content' => 'Content 1'],
    ['user_id' => 1, 'title' => 'Article 2', 'content' => 'Content 2'],
];

$fluent->insertInto('article')->values($rows)->execute();
```

### Using SQL literals

For database functions like `NOW()` or `CURRENT_TIMESTAMP`:

```php
use Envms\FluentPDO\Literal;

$fluent->insertInto('article', [
    'user_id'    => 1,
    'created_at' => new Literal('NOW()'),
    'title'      => 'My Article',
    'content'    => 'Content...'
])->execute();
```

### INSERT IGNORE

```php
$fluent->insertInto('article', $values)
    ->ignore()
    ->execute();
```

### ON DUPLICATE KEY UPDATE (MySQL)

```php
$fluent->insertInto('article', ['id' => 1, 'title' => 'Updated Title'])
    ->onDuplicateKeyUpdate([
        'title'   => 'Updated Title',
        'content' => new Literal('VALUES(content)'),
    ])
    ->execute();
```

---

## DELETE

### Basic delete

```php
// Delete with WHERE
$fluent->deleteFrom('user')
    ->where('id', 1)
    ->execute();

// Shortcut: delete by primary key
$fluent->deleteFrom('user', 1)->execute();
```

### Delete with composite primary key

```php
$fluent->delete('order_item', [
    'order_id'   => 123,
    'product_id' => 456
])->execute();
```

### Delete with JOIN

```php
// Delete from multiple tables with JOIN
$fluent->delete('t1, t2')
    ->from('t1')
    ->innerJoin('t2 ON t1.id = t2.id')
    ->where('t1.id', 1)
    ->execute();
```

### Delete with ORDER BY and LIMIT

```php
$fluent->deleteFrom('log')
    ->where('created_at < ?', $cutoffDate)
    ->orderBy('id')
    ->limit(1000)
    ->execute();
```

### DELETE IGNORE

```php
$fluent->deleteFrom('user')
    ->ignore()
    ->where('id', 1)
    ->execute();
```

**Note:** Delete and Update queries require a WHERE clause to prevent accidental data loss. FluentPDO throws an exception if WHERE is empty.

---

## UPDATE

### Basic update

```php
// Single field
$fluent->update('user')
    ->set('name', 'New Name')
    ->where('id', 1)
    ->execute();

// Multiple fields
$fluent->update('user')
    ->set(['name' => 'New Name', 'type' => 'admin'])
    ->where('id', 1)
    ->execute();
```

### Update with SQL literal

```php
use Envms\FluentPDO\Literal;

$fluent->update('article')
    ->set('published_at', new Literal('NOW()'))
    ->where('id', 1)
    ->execute();
```

### Update with JOIN

```php
$fluent->update('user')
    ->leftJoin('country ON country.id = user.country_id')
    ->set(['name' => 'Updated'])
    ->where('country.id', 1)
    ->execute();
```

### Update with smart join

```php
// WHERE references country — join is created automatically
$fluent->update('user')
    ->set(['type' => 'author'])
    ->where('country.id', 1)
    ->execute();
```

### Shortcut: update by primary key

```php
$fluent->update('user', ['name' => 'New Name'], 1)->execute();
```

---

## Chunking large result sets

Process large tables without loading everything into memory:

```php
$fluent->from('large_table')
    ->where('processed = ?', 0)
    ->chunk(1000, function (array $rows) {
        foreach ($rows as $row) {
            processRow($row);
        }
    });
```

---

## Closing the connection

```php
$fluent->close();
```
