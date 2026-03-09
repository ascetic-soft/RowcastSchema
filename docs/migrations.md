---
title: Migrations
layout: default
nav_order: 5
---

# Migrations
{: .no_toc }

Generated migration format, SchemaBuilder API, and the migration runner.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Generated Migration Format

When you run `rowcast-schema diff`, a PHP class is generated:

```php
<?php

declare(strict_types=1);

use AsceticSoft\RowcastSchema\Migration\AbstractMigration;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;
use AsceticSoft\RowcastSchema\SchemaBuilder\TableBuilder;

final class Migration_20260306_143022_CreateUsersTable extends AbstractMigration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->createTable('users', function (TableBuilder $table) {
            $table->column('id', 'integer')->primaryKey()->autoIncrement();
            $table->column('email', 'string'); // default length: 255
            $table->column('created_at', ColumnType::Datetime)->default('CURRENT_TIMESTAMP');
        });
        $schema->addIndex('users', 'idx_users_email', ['email'], unique: true);
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropTable('users');
    }
}
```

### File naming

Migration files follow the pattern:

```
Migration_YYYYMMDD_HHMMSS_Description.php
```

---

## MigrationInterface

Every migration implements:

```php
interface MigrationInterface
{
    public function up(SchemaBuilder $schema): void;
    public function down(SchemaBuilder $schema): void;
}
```

The `AbstractMigration` base class is provided for convenience but carries no logic — it simply implements the interface.

---

## SchemaBuilder API

`SchemaBuilder` is an **operation collector**. Calling its methods does not execute SQL directly — it accumulates `Operation[]` objects that the `MigrationRunner` later compiles and executes.

### Table operations

| Method | Description |
|:-------|:-----------|
| `createTable(string $name, callable $callback)` | Define a new table using `TableBuilder` |
| `dropTable(string $name)` | Drop a table |
| `addColumn(string $table, Column $column)` | Add a column |
| `dropColumn(string $table, string $column)` | Drop a column |
| `alterColumn(string $table, Column $old, Column $new)` | Alter a column definition |

### Index operations

| Method | Description |
|:-------|:-----------|
| `addIndex(string $table, string $name, array $columns, bool $unique)` | Create an index |
| `dropIndex(string $table, string $name)` | Drop an index |

### Foreign key operations

| Method | Description |
|:-------|:-----------|
| `addForeignKey(string $table, ForeignKey $fk)` | Add a foreign key |
| `dropForeignKey(string $table, string $name)` | Drop a foreign key |

---

## TableBuilder (Fluent API)

Inside `createTable()`, use the unified `column()` API:

```php
$schema->createTable('products', function (TableBuilder $table) {
    $table->column('id', 'uuid')->primaryKey();
    $table->column('name', 'string'); // default length: 255
    $table->column('price', 'decimal')->precision(10, 2)->unsigned();
    $table->column('description', 'text')->nullable();
    $table->column('created_at', ColumnType::Datetime)->default('CURRENT_TIMESTAMP');
    $table->column('active', 'boolean')->default(true);
    $table->column('meta', 'jsonb'); // custom raw DB type
});
```

`column()` accepts:

- `ColumnType` enum values (`ColumnType::String`, `ColumnType::Datetime`, ...),
- known abstract type strings (`'string'`, `'integer'`, ...),
- custom raw database types (`'jsonb'`, `'citext'`, `'numeric(20,6)'`, ...).

### Raw SQL

When needed, execute arbitrary SQL in migration methods:

```php
public function up(SchemaBuilder $schema): void
{
    $schema->sql("UPDATE users SET status = 'active' WHERE status IS NULL");
}
```

### Column modifiers

All return `ColumnBuilder` (fluent):

| Method | Description |
|:-------|:-----------|
| `nullable()` | Allow NULL |
| `default(mixed)` | Set default value |
| `primaryKey()` | Mark as primary key |
| `autoIncrement()` | Enable auto-increment |
| `unsigned()` | Unsigned integer |
| `comment(string)` | Column comment |
| `values(array)` | Enum values |

---

## Migration Runner

`MigrationRunner` orchestrates migration execution:

1. Loads migration files via `MigrationLoader` (sorted by timestamp).
2. Checks applied state via `MigrationRepositoryInterface` (queries `_rowcast_migrations` table).
3. Executes `up()` or `down()` on each migration.
4. Compiles operations through the SQL `Platform` for the current database driver.
5. Wraps in a transaction when `Platform::supportsDdlTransactions()` returns `true` (PostgreSQL).

---

## Migration State Table

Applied migrations are tracked in `_rowcast_migrations` (auto-created):

| Column | Type | Description |
|:-------|:-----|:-----------|
| `version` | VARCHAR(255) PK | Migration class name |
| `applied_at` | DATETIME | When the migration was applied |

---

## Rename Operations

{: .important }
Rename detection is **not** automatic. The differ cannot distinguish a rename from a drop + create. If you rename a table or column in your schema, the generated migration will contain a `drop` + `create`. You can manually edit the migration to use a rename statement if needed.
