# Rowcast Schema

[![CI](https://github.com/ascetic-soft/RowcastSchema/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/RowcastSchema/actions/workflows/ci.yml)
[![GitHub Pages](https://img.shields.io/badge/docs-gh--pages-blue)](https://ascetic-soft.github.io/RowcastSchema/)
[![codecov](https://codecov.io/gh/ascetic-soft/RowcastSchema/graph/badge.svg)](https://codecov.io/gh/ascetic-soft/RowcastSchema)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![Total Downloads](https://img.shields.io/packagist/dt/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/rowcast-schema/php)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![License](https://img.shields.io/packagist/l/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)

`ascetic-soft/rowcast-schema` is a schema-first migration toolkit for PDO databases, designed to be friendly with Rowcast.

For Russian documentation, see [README.ru.md](README.ru.md).

**Documentation:** [English](https://ascetic-soft.github.io/RowcastSchema/) | [Русский](https://ascetic-soft.github.io/RowcastSchema/ru/)

## What it does

Core workflow:
- define your database structure in `schema.php` (or optional `schema.yaml`);
- compare schema against a live database (`diff`);
- generate PHP migrations automatically;
- apply or rollback migrations;
- check schema/database sync status (`status`).

## Features

- PHP schema definition without extra dependencies (`schema.php`)
- optional YAML schema definition (`schema.yaml` / `schema.yml`) via `symfony/yaml`
- live database introspection through PDO
- schema diff (configured schema file vs actual DB structure)
- PHP migration generation (`up`/`down`)
- migration state tracking in `_rowcast_migrations`
- MySQL, PostgreSQL, and SQLite support
- SQLite rebuild pipeline for unsupported DDL operations

## Installation

```bash
composer require ascetic-soft/rowcast-schema
```

## Configuration

Create `rowcast-schema.php` in your project root:

```php
<?php

return [
    'connection' => [
        'dsn' => 'mysql:host=localhost;dbname=app',
        'username' => 'root',
        'password' => 'secret',
        // 'options' => [],
    ],
    'schema' => __DIR__ . '/schema.php',
    'migrations' => __DIR__ . '/migrations',
];
```

### Optional YAML support

YAML support is optional. Install it only if you want `schema.yaml`:

```bash
composer require symfony/yaml
```

## Schema formats

### `schema.php` (default, dependency-free)

```php
<?php

return [
    'tables' => [
        'users' => [
            'columns' => [
                'id' => [
                    'type' => 'integer',
                    'primaryKey' => true,
                    'autoIncrement' => true,
                ],
                'email' => [
                    'type' => 'string',
                    'length' => 255,
                ],
                'created_at' => [
                    'type' => 'datetime',
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ],
            'indexes' => [
                'idx_users_email' => [
                    'columns' => ['email'],
                    'unique' => true,
                ],
            ],
        ],
    ],
];
```

### `schema.yaml` / `schema.yml` (optional)

```yaml
tables:
  users:
    columns:
      id:
        type: integer
        primaryKey: true
        autoIncrement: true
      email:
        type: string
        length: 255
      created_at:
        type: datetime
        default: CURRENT_TIMESTAMP
    indexes:
      idx_users_email:
        columns: [email]
        unique: true
```

### Supported abstract types

- `integer`, `smallint`, `bigint`
- `string`, `text`
- `boolean`
- `decimal`, `float`, `double`
- `datetime`, `date`, `time`, `timestamp`
- `uuid`, `json`, `binary`, `enum`

### Column properties

- `type` (required)
- `nullable` (default: `false`)
- `default`
- `primaryKey`
- `autoIncrement`
- `length`, `precision`, `scale`
- `unsigned`
- `comment`
- `values` (for `enum`)

## CLI commands

Entry point:

```bash
vendor/bin/rowcast-schema
```

### Diff

Generate migration from schema changes:

```bash
vendor/bin/rowcast-schema diff
```

Dry-run only:

```bash
vendor/bin/rowcast-schema diff --dry-run
```

### Migrate

Apply pending migrations:

```bash
vendor/bin/rowcast-schema migrate
```

### Rollback

Rollback the latest migration:

```bash
vendor/bin/rowcast-schema rollback
```

Rollback the last N migrations:

```bash
vendor/bin/rowcast-schema rollback --step=3
```

### Status

Show migration state and schema sync status:

```bash
vendor/bin/rowcast-schema status
```

## How it works

1. The parser reads the configured schema file (`.php`, `.yaml`, `.yml`) and builds an internal schema model.
2. The introspector reads the current structure from the database.
3. `SchemaDiffer` computes an operation list (`create`, `drop`, `add`, `alter`, etc.).
4. The generator creates a PHP migration file.
5. `MigrationRunner` executes operations through a SQL platform implementation.
6. Applied versions are stored in `_rowcast_migrations`.

## SQLite notes

SQLite has limited DDL support (`ALTER TABLE`, FK operations).  
For unsupported cases, Rowcast Schema uses a rebuild pipeline:
- create a temporary table with the new structure;
- copy data;
- swap tables;
- recreate indexes and foreign keys.

This enables complex schema updates on SQLite in an automated way.

## Project status

The project is under active development. The API may evolve in future versions.

