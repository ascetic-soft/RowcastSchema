---
title: Home
layout: home
nav_order: 1
---

# Rowcast Schema

{: .fs-9 }

Schema-first migration toolkit for PDO databases, friendly to Rowcast.
{: .fs-6 .fw-300 }

[![CI](https://github.com/ascetic-soft/RowcastSchema/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/RowcastSchema/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/RowcastSchema/graph/badge.svg)](https://codecov.io/gh/ascetic-soft/RowcastSchema)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/rowcast-schema/php)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![License](https://img.shields.io/packagist/l/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)

[Get Started]({{ '/docs/getting-started.html' | relative_url }}){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[Русский]({{ '/ru/' | relative_url }}){: .btn .btn-outline .fs-5 .mb-4 .mb-md-0 .mr-2 }
[View on GitHub](https://github.com/ascetic-soft/RowcastSchema){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## What is Rowcast Schema?

Rowcast Schema is a **schema-first migration toolkit** for PHP 8.4+. Define your database structure in a PHP or YAML file, compare it against a live database, and automatically generate PHP migration classes with `up()` and `down()` methods.

### Key Highlights

- **Schema-first** — define your tables, columns, indexes, and foreign keys in `schema.php` or `schema.yaml`
- **Auto diff** — compare schema against a live database and generate migrations automatically
- **Zero required dependencies** — only PHP 8.4 and ext-pdo; YAML support is optional via `symfony/yaml`
- **Multi-database** — MySQL, PostgreSQL, and SQLite support out of the box
- **SQLite rebuild pipeline** — automatically handles complex DDL changes that SQLite can't do natively
- **Fluent migration API** — generated migrations use a clean `SchemaBuilder` with `up()` / `down()`
- **PHPStan Level 9** — fully statically analyzed codebase
- **Friendly to Rowcast** — shares PDO connection, follows the same conventions

---

## Quick Example

Define your schema in `schema.php`:

```php
<?php

return [
    'tables' => [
        'users' => [
            'columns' => [
                'id' => ['type' => 'integer', 'primaryKey' => true, 'autoIncrement' => true],
                'email' => ['type' => 'string', 'length' => 255],
                'created_at' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],
            ],
            'indexes' => [
                'idx_users_email' => ['columns' => ['email'], 'unique' => true],
            ],
        ],
    ],
];
```

Then generate and apply migrations:

```bash
vendor/bin/rowcast-schema diff      # generate migration from schema changes
vendor/bin/rowcast-schema migrate   # apply pending migrations
vendor/bin/rowcast-schema status    # check sync status
```

---

## Requirements

- **PHP** >= 8.4
- **PDO** extension

## Installation

```bash
composer require ascetic-soft/rowcast-schema
```

---

## Documentation

<div class="grid-container" markdown="0">
  <div class="grid-item">
    <h3><a href="{{ '/docs/getting-started.html' | relative_url }}">Getting Started</a></h3>
    <p>Installation, configuration, and first migration in 5 minutes.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/schema.html' | relative_url }}">Schema Definition</a></h3>
    <p>PHP and YAML schema formats, column types, indexes, and foreign keys.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/cli.html' | relative_url }}">CLI Commands</a></h3>
    <p>diff, migrate, rollback, and status commands.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/migrations.html' | relative_url }}">Migrations</a></h3>
    <p>Generated migration format, SchemaBuilder API, and migration runner.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/sqlite.html' | relative_url }}">SQLite Support</a></h3>
    <p>Rebuild pipeline for complex DDL changes on SQLite.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/docs/api-reference.html' | relative_url }}">API Reference</a></h3>
    <p>Complete reference for all public classes and interfaces.</p>
  </div>
</div>
