# Fixes and Improvements (vs. Previous Versions)

This document lists bug fixes and feature improvements implemented from community [GitHub issues](https://github.com/envms/fluentpdo/issues) and [pull requests](https://github.com/envms/fluentpdo/pulls).

## Bug Fixes

### PDO::quote() fallback for PDO_ODBC ([#344](https://github.com/envms/fluentpdo/issues/344))
When using PDO_ODBC, `PDO::quote()` returns `false` instead of throwing. FluentPDO now checks for `false` and falls back to manual escaping in `AbstractDialect::quoteValue()`.

**Technical change:** Modified `AbstractDialect::quoteValue()` to check `if ($quoted === false)` before returning the result from `PDO::quote()`.

### deleteFrom() composite primary key type hint ([#339](https://github.com/envms/fluentpdo/issues/339))
`deleteFrom()` now accepts `int|array|null` for the primary key parameter, matching `delete()`. Enables `$fluent->deleteFrom('table', ['pk1' => 1, 'pk2' => 2])` for composite keys.

**Technical change:** Updated method signature in `Query::deleteFrom()` from `(?int $primaryKey = null)` to `(int|array|null $primaryKey = null)`.

### Complex JOIN with subquery clauses ([#341](https://github.com/envms/fluentpdo/issues/341), [PR #342](https://github.com/envms/fluentpdo/pull/342))
Multiple LEFT JOINs with subquery expressions (e.g. `(SELECT ...) alias ON condition`) are now correctly parsed and deduplicated. Fixed JOIN deduplication logic in `Common::addJoinStatements()` to properly handle subquery aliases.

## New Features

### BETWEEN method ([#323](https://github.com/envms/fluentpdo/issues/323))
Adds `$query->between('column', $min, $max)` and `$query->betweenOr('column', $min, $max)` for range conditions. Produces `column BETWEEN ? AND ?` with proper parameter binding.

### INSERT SELECT support ([#318](https://github.com/envms/fluentpdo/issues/318))
`$fluent->insertInto('table')->values($selectQuery, ['col1','col2'])` produces `INSERT INTO table (col1,col2) SELECT ...` with proper parameter merging.

### RETURNING clause for PostgreSQL ([#222](https://github.com/envms/fluentpdo/issues/222))
`$query->returning('col1')` or `->returning(['col1','col2'])` appends `RETURNING col1, col2` to UPDATE/INSERT queries (PostgreSQL only). Throws exception for unsupported dialects.

### replaceInto() method ([#219](https://github.com/envms/fluentpdo/issues/219))
`replaceInto()` produces `REPLACE INTO` (MySQL) or `INSERT OR REPLACE` (SQLite). Throws exception for unsupported dialects.

### transaction() method
Adds `$fluent->transaction(callable $callback)` for executing multiple operations atomically. Automatically handles begin/commit/rollback and joins existing transactions when nested.