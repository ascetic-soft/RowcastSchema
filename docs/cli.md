---
title: CLI Commands
layout: default
nav_order: 4
---

# CLI Commands
{: .no_toc }

All available commands for `vendor/bin/rowcast-schema`.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Overview

The CLI reads configuration from `rowcast-schema.php` in the current working directory.

```bash
vendor/bin/rowcast-schema <command> [options]
```

---

## diff

Compare the schema file against the live database and generate a migration.

```bash
vendor/bin/rowcast-schema diff
```

### Options

| Option | Description |
|:-------|:-----------|
| `--dry-run` | Print operations to stdout without generating a migration file |

### How it works

1. Parses the configured schema file → builds the desired `Schema` model.
2. Introspects the live database via PDO → builds the current `Schema` model.
3. `SchemaDiffer` computes a list of operations (create, drop, add, alter, etc.).
4. `MigrationGenerator` creates a PHP migration class in the configured migrations directory.

---

## migrate

Apply all pending migrations in timestamp order.

```bash
vendor/bin/rowcast-schema migrate
```

### How it works

1. `MigrationLoader` scans the migrations directory and sorts files by timestamp prefix.
2. `MigrationRunner` checks which migrations have already been applied (stored in `_rowcast_migrations` table).
3. Each pending migration's `up()` method is executed.
4. For PostgreSQL, each migration is wrapped in a transaction (DDL-safe). MySQL does not support transactional DDL.

---

## rollback

Roll back the latest migration, or multiple.

```bash
# Roll back the last migration
vendor/bin/rowcast-schema rollback

# Roll back the last 3 migrations
vendor/bin/rowcast-schema rollback --step=3
```

### Options

| Option | Description |
|:-------|:-----------|
| `--step=N` | Number of migrations to roll back (default: 1) |

---

## status

Show migration state and schema synchronization status.

```bash
vendor/bin/rowcast-schema status
```

### Output includes

- List of applied migrations with timestamps
- List of pending (not yet applied) migrations
- Live diff between the schema file and the current database
- Summary: "Schema is in sync" or a list of differences
