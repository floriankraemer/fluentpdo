# FluentPDO [![Build Status](https://secure.travis-ci.org/envms/fluentpdo.png?branch=master)](http://travis-ci.org/envms/fluentpdo) [![Maintainability](https://api.codeclimate.com/v1/badges/19210ca91c7055b89705/maintainability)](https://codeclimate.com/github/fpdo/fluentpdo/maintainability)

FluentPDO is a PHP SQL query builder using PDO. It's a quick and light library featuring a smart join builder, which automatically creates table joins for you.

## Features

- Easy interface for creating robust queries
- **Multi-dialect SQL support** - MySQL, PostgreSQL, SQLite, and SQL Server
- **Memory-efficient processing** - Chunk large result sets without memory exhaustion
- Ability to build complex SELECT, INSERT, UPDATE & DELETE queries with little code
- Type hinting for magic methods with code completion in smart IDEs
- **Composite primary key support** for complex database schemas
- **ODBC compatibility** for databases without native PDO::quote() support

## Versions

#### Version 3.x

The latest major release of FluentPDO with significant performance and memory improvements.
Officially supports PHP 8.3+, includes multi-dialect SQL support, and features a complete
rewrite for better maintainability. See [UPGRADE-3.0.md](UPGRADE-3.0.md) for migration guide.

#### Version 2.x

The previous stable release of FluentPDO. Supports PHP 7.1 to PHP 8.2.
This version is still maintained for compatibility but no new features will be added.

#### Version 1.x

The legacy release of FluentPDO. It is no longer supported and will not be maintained or updated.
This version works with PHP 5.4 to 7.1.

## Documentation

- [Query Examples](docs/query-examples.md) — INSERT, DELETE, SELECT, and JOIN examples
- [Upgrading to 3.0](docs/UPGRADE-3.0.md) — Migration guide from 2.x

## Reference

[Sitepoint - Getting Started with FluentPDO](http://www.sitepoint.com/getting-started-fluentpdo/)

## Installation

### Composer

The preferred way to install FluentPDO is via [composer](http://getcomposer.org/).

Add the following line in your `composer.json` file:

	"require": {
		...
		"envms/fluentpdo": "^3.0.0"
	}

update your dependencies with `composer update`, and you're done!

### Download Zip

If you prefer not to use composer, download the latest release, create the directory `Envms/FluentPDO` in your library directory, and drop this repository into it. Finally, add:

```php
require '[lib-dir]/Envms/FluentPDO/src/Query.php';
```

to the top of your application. **Note:** You will need an autoloader to use FluentPDO without changing its source code.

## Getting Started

Create a new PDO instance, and pass the instance to FluentPDO:

```php
$pdo = new PDO('mysql:dbname=fluentdb', 'user', 'password');
$fluent = new \Envms\FluentPDO\Query($pdo);
```

Then, creating queries is quick and easy:

```php
$query = $fluent->from('comment')
             ->where('article.published_at > ?', $date)
             ->orderBy('published_at DESC')
             ->limit(5);
```

which would build the query below:

```mysql
SELECT comment.*
FROM comment
LEFT JOIN article ON article.id = comment.article_id
WHERE article.published_at > ?
ORDER BY article.published_at DESC
LIMIT 5
```

To get data from the select, all we do is loop through the returned array:

```php
foreach ($query as $row) {
    echo "$row['title']\n";
}
```

## Using the Smart Join Builder

Let's start with a traditional join, below:

```php
$query = $fluent->from('article')
             ->leftJoin('user ON user.id = article.user_id')
             ->select('user.name');
```

That's pretty verbose, and not very smart. If your tables use proper primary and foreign key names, you can shorten the above to:

```php
$query = $fluent->from('article')
             ->leftJoin('user')
             ->select('user.name');
```

That's better, but not ideal. However, it would be even easier to **not write any joins**:

```php
$query = $fluent->from('article')
             ->select('user.name');
```

Awesome, right? FluentPDO is able to build the join for you, by you prepending the foreign table name to the requested column.

All three snippets above will create the exact same query:

```mysql
SELECT article.*, user.name 
FROM article 
LEFT JOIN user ON user.id = article.user_id
```

##### Close your connection

Finally, it's always a good idea to free resources as soon as they are done with their duties:
 
 ```php
$fluent->close();
```

## Multi-Dialect SQL Support

FluentPDO automatically detects your database dialect and generates appropriate SQL:

```php
// MySQL
$pdo = new PDO('mysql:host=localhost;dbname=test');
$fluent = new Query($pdo); // Uses MySQL dialect

// PostgreSQL
$pdo = new PDO('pgsql:host=localhost;dbname=test');
$fluent = new Query($pdo); // Uses PostgreSQL dialect

// SQLite
$pdo = new PDO('sqlite:database.db');
$fluent = new Query($pdo); // Uses SQLite dialect

// SQL Server via ODBC
$pdo = new PDO('odbc:Driver={SQL Server};Server=localhost;Database=test');
$fluent = new Query($pdo); // Uses SQL Server dialect
```

## Memory-Efficient Large Dataset Processing

Process large result sets without loading everything into memory:

```php
$fluent->from('large_table')
    ->chunk(1000, function($rows) {
        foreach ($rows as $row) {
            // Process each row
            processRow($row);
        }
    });
```

This processes data in chunks of 1000 rows, keeping memory usage low.

## CRUD Query Examples

##### SELECT

```php
$query = $fluent->from('article')->where('id', 1)->fetch();
$query = $fluent->from('user', 1)->fetch(); // shorter version if selecting one row by primary key
```

##### INSERT

```php
$values = array('title' => 'article 1', 'content' => 'content 1');

$query = $fluent->insertInto('article')->values($values)->execute();
$query = $fluent->insertInto('article', $values)->execute(); // shorter version
```

##### UPDATE

```php
$set = array('published_at' => new FluentLiteral('NOW()'));

$query = $fluent->update('article')->set($set)->where('id', 1)->execute();
$query = $fluent->update('article', $set, 1)->execute(); // shorter version if updating one row by primary key
```

##### DELETE

```php
$query = $fluent->deleteFrom('article')->where('id', 1)->execute();
$query = $fluent->deleteFrom('article', 1)->execute(); // shorter version if deleting one row by primary key
```

***Note**: INSERT, UPDATE and DELETE queries will only run after you call `->execute()`*

## License

Free for commercial and non-commercial use under the [Apache 2.0](http://www.apache.org/licenses/LICENSE-2.0.html) or [GPL 2.0](http://www.gnu.org/licenses/gpl-2.0.html) licenses.
