# Rowcast Schema

[![CI](https://github.com/ascetic-soft/RowcastSchema/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/RowcastSchema/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/RowcastSchema/graph/badge.svg?token=6GZCAEXM6F)](https://codecov.io/gh/ascetic-soft/RowcastSchema)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![Total Downloads](https://img.shields.io/packagist/dt/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/rowcast-schema/php)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![License](https://img.shields.io/packagist/l/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)

Schema-first migration toolkit for PDO databases (PHP 8.4+).

Zero external dependencies. Describe your database structure in a PHP file, diff it against a live database, and generate reversible PHP migrations automatically. Designed to work alongside [Rowcast](https://github.com/ascetic-soft/Rowcast).

**Documentation:** [English](https://ascetic-soft.github.io/RowcastSchema/) | [Русский](https://ascetic-soft.github.io/RowcastSchema/ru/)

## Requirements

- PHP >= 8.4
- PDO extension

## Installation

```bash
composer require ascetic-soft/rowcast-schema
```

Optional YAML schema support:

```bash
composer require symfony/yaml
```

## Quick Start

### 1. Create a configuration file

Create `rowcast-schema.php` in your project root (default path):

```php
<?php

return [
    'connection' => [
        'dsn' => 'mysql:host=localhost;dbname=app',
        'username' => 'root',
        'password' => 'secret',
    ],
    'schema' => __DIR__ . '/schema.php',
    'migrations' => __DIR__ . '/migrations',
    'migration_table' => '_rowcast_migrations',
    'ignore_tables' => [
        '/^tmp_/',
        '/^audit_/',
        static fn (string $table): bool => str_ends_with($table, '_shadow'),
    ],
];
```

You can also store config in a custom location and pass it via CLI:

```bash
vendor/bin/rowcast-schema --config=database/rowcast-schema.php diff
```

Config may return either an array or a factory `Closure(string $projectDir): array`.
Factory mode is useful for loading environment variables from your app kernel/project root.

`migration_table` defines the table used to track applied migrations.
This table is always ignored automatically in schema diff.
Use `ignore_tables` to add custom ignore rules: regex strings and/or callbacks.

### 2. Define a schema

`schema.php`:

```php
<?php

return [
    'tables' => [
        'users' => [
            'columns' => [
                'id' => ['type' => 'integer', 'primaryKey' => true, 'autoIncrement' => true],
                'email' => ['type' => 'string', 'length' => 255],
                'status' => ['type' => 'enum', 'values' => ['active', 'banned']],
                'created_at' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],
            ],
            'indexes' => [
                'idx_users_email' => ['columns' => ['email'], 'unique' => true],
            ],
        ],
    ],
];
```

### 3. Generate and apply a migration

```bash
# Generate a migration from schema diff
vendor/bin/rowcast-schema diff

# Apply pending migrations
vendor/bin/rowcast-schema migrate

# Check sync status
vendor/bin/rowcast-schema status
```

Global CLI option:

- `--config=path` (or `--config path`) — use a custom configuration file path.

## Schema Definition

### Supported formats

| Format | File | Dependency |
|--------|------|------------|
| PHP array (default) | `schema.php` | None |
| YAML | `schema.yaml` / `schema.yml` | `symfony/yaml` |

The format is detected automatically by file extension.

### Abstract column types

`integer`, `smallint`, `bigint`, `string`, `text`, `boolean`, `decimal`, `float`, `double`, `datetime`, `date`, `time`, `timestamp`, `uuid`, `json`, `binary`, `enum`

### Column properties

| Property | Default | Description |
|----------|---------|-------------|
| `type` | *(required)* | Abstract column type |
| `nullable` | `false` | Allow NULL values |
| `default` | — | Default value |
| `primaryKey` | `false` | Mark as primary key |
| `autoIncrement` | `false` | Auto-increment |
| `length` | — | String/binary length |
| `precision` / `scale` | — | Decimal precision |
| `unsigned` | `false` | Unsigned integer |
| `comment` | — | Column comment |
| `values` | — | Enum values list |

For `type: string`, default length is `255` when `length` is omitted.

### Migration Builder API

Generated migrations and manual migration code use a unified column API:

```php
$table->column('id', 'integer')->primaryKey();
$table->column('email', 'string'); // VARCHAR(255) by default
$table->column('status', ColumnType::String)->length(50);
$table->column('payload', 'jsonb'); // custom raw DB type
```

`column()` accepts:

- `ColumnType` enum values (`ColumnType::String`, `ColumnType::Datetime`, ...)
- known abstract type strings (`'string'`, `'integer'`, ...)
- any custom database type string (`'jsonb'`, `'citext'`, `'numeric(20,6)'`, ...)

### Foreign keys

```php
'foreignKeys' => [
    'fk_posts_user' => [
        'columns' => ['user_id'],
        'referenceTable' => 'users',
        'referenceColumns' => ['id'],
        'onDelete' => 'CASCADE',
        'onUpdate' => 'SET NULL',
    ],
],
```

## CLI Commands

```bash
vendor/bin/rowcast-schema <command> [options]
```

| Command | Description |
|---------|-------------|
| `diff` | Generate a migration from schema changes |
| `diff --dry-run` | Preview operations without generating a file |
| `migrate` | Apply all pending migrations |
| `rollback` | Rollback the latest migration |
| `rollback --step=N` | Rollback the last N migrations |
| `status` | Show migration state and schema sync status |

## How It Works

1. **Parse** — reads `schema.php` (or `.yaml`) and builds an internal `Schema` model.
2. **Introspect** — reads the current database structure via PDO.
3. **Diff** — `SchemaDiffer` computes a list of operations (create, drop, add, alter, etc.).
4. **Generate** — creates a PHP migration class with `up()` and `down()` methods.
5. **Execute** — `MigrationRunner` applies operations through a database-specific SQL platform.
6. **Track** — applied versions are stored in the `_rowcast_migrations` table.

## SQLite Support

SQLite has limited DDL capabilities (`ALTER TABLE`, foreign keys). For unsupported operations, Rowcast Schema uses a **rebuild pipeline**:

1. Create a temporary table with the new structure
2. Copy data from the original table
3. Drop the original and rename the temporary table
4. Recreate indexes and foreign keys

This enables complex schema changes on SQLite transparently.

## Architecture

```
AsceticSoft\RowcastSchema\
├── Schema\
│   ├── Schema                        # Root schema model
│   ├── Table, Column, Index, ForeignKey  # Schema components
│   └── ColumnType                    # Abstract type enum
├── Parser\
│   ├── SchemaParserInterface         # Parser contract
│   ├── PhpSchemaParser               # PHP array parser (default)
│   ├── YamlSchemaParser              # YAML parser (optional)
│   └── ArraySchemaBuilder            # Shared array → Schema builder
├── Introspector\
│   ├── IntrospectorInterface         # Introspector contract
│   ├── IntrospectorFactory           # PDO driver → introspector
│   ├── MysqlIntrospector             # MySQL introspection
│   ├── PostgresIntrospector          # PostgreSQL introspection
│   └── SqliteIntrospector            # SQLite introspection
├── Diff\
│   ├── SchemaDiffer                  # Schema comparison engine
│   └── Operation\
│       ├── OperationInterface        # Operation contract
│       ├── CreateTable, DropTable    # Table-level operations
│       ├── AddColumn, AlterColumn, DropColumn
│       ├── AddIndex, DropIndex
│       └── AddForeignKey, DropForeignKey
├── Platform\
│   ├── PlatformInterface             # SQL generation contract
│   ├── PlatformFactory               # PDO driver → platform
│   ├── AbstractPlatform              # Shared SQL logic
│   ├── MysqlPlatform                 # MySQL DDL
│   ├── PostgresPlatform              # PostgreSQL DDL
│   └── SqlitePlatform                # SQLite DDL (with rebuild)
├── Migration\
│   ├── MigrationInterface            # Migration contract
│   ├── AbstractMigration             # Base migration class
│   ├── MigrationGenerator            # PHP migration file generator
│   ├── MigrationLoader               # Loads migration files from disk
│   ├── MigrationRunner               # Applies/rollbacks migrations
│   ├── MigrationRepositoryInterface  # Repository contract
│   ├── DatabaseMigrationRepository   # Tracks applied migrations in DB
│   └── SqliteTableRebuilder          # SQLite rebuild pipeline
├── SchemaBuilder\
│   ├── SchemaBuilder                 # Fluent API for migration operations
│   ├── TableBuilder                  # Fluent table/column definition
│   └── ColumnBuilder                 # Fluent column properties
├── TypeMapper\
│   ├── TypeMapperInterface           # Type mapper contract
│   ├── MysqlTypeMapper               # MySQL type mapping
│   ├── PostgresTypeMapper            # PostgreSQL type mapping
│   └── SqliteTypeMapper              # SQLite type mapping
├── Pdo\
│   └── PdoDriverResolver            # Centralized PDO driver detection
└── Cli\
    ├── Application                   # CLI entry point
    ├── Config                        # Configuration loader
    └── Command\
        ├── CommandInterface          # Command contract
        ├── DiffCommand               # diff command
        ├── MigrateCommand            # migrate command
        ├── RollbackCommand           # rollback command
        └── StatusCommand             # status command
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

Static analysis:

```bash
vendor/bin/phpstan analyse
```

## License

MIT
