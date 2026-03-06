---
title: Getting Started
layout: default
nav_order: 2
---

# Getting Started
{: .no_toc }

Get up and running with Rowcast Schema in under 5 minutes.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Installation

Install via Composer:

```bash
composer require ascetic-soft/rowcast-schema
```

**Requirements:**
- PHP >= 8.4
- PDO extension

### Optional: YAML support

If you prefer YAML schema format over PHP arrays:

```bash
composer require symfony/yaml
```

---

## Configuration

Create `rowcast-schema.php` in your project root:

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
];
```

| Key | Description |
|:----|:-----------|
| `connection.dsn` | PDO connection string |
| `connection.username` | Database username |
| `connection.password` | Database password |
| `connection.options` | Optional PDO options array |
| `schema` | Path to schema file (`.php`, `.yaml`, or `.yml`) |
| `migrations` | Directory for generated migration files |

---

## Define Your Schema

Create `schema.php`:

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

---

## Generate Your First Migration

```bash
vendor/bin/rowcast-schema diff
```

This compares your schema against the current database and generates a PHP migration file in the `migrations/` directory.

Preview changes without generating a file:

```bash
vendor/bin/rowcast-schema diff --dry-run
```

---

## Apply Migrations

```bash
vendor/bin/rowcast-schema migrate
```

This applies all pending migrations in timestamp order.

---

## Check Status

```bash
vendor/bin/rowcast-schema status
```

Shows which migrations are applied, which are pending, and whether the schema is in sync with the database.

---

## What's Next?

- [Schema Definition]({{ '/docs/schema.html' | relative_url }}) — all column types, indexes, and foreign keys
- [CLI Commands]({{ '/docs/cli.html' | relative_url }}) — full CLI reference
- [Migrations]({{ '/docs/migrations.html' | relative_url }}) — generated migration format and SchemaBuilder API
- [SQLite Support]({{ '/docs/sqlite.html' | relative_url }}) — rebuild pipeline for complex DDL
- [API Reference]({{ '/docs/api-reference.html' | relative_url }}) — complete class reference
