# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
make test        # Run PHPUnit tests
make cs-fix      # Auto-fix code style (PSR-12 + PHP 8.4 rules)
make cs-check    # Check code style (dry-run)
make phpstan     # Static analysis at level 9
make ci          # cs-check + phpstan + test (full CI pipeline)
make install     # composer install
```

To run a single test file:
```bash
vendor/bin/phpunit tests/Path/To/SomeTest.php
```

## Code Standards

- PHP 8.4+, `declare(strict_types=1)` required in all files
- PHPStan level 9 — no untyped code, no mixed, no suppression without cause
- Short array syntax `[]`, PSR-12 style enforced by CS Fixer

## Architecture

**Rowcast Schema** is a schema-first migration toolkit for PDO databases. Users define DB structure in PHP arrays, YAML, or PHP attributes; the library diffs the definition against a live DB and generates reversible PHP migration files.

### Core Pipeline

```
Schema definition (PHP / YAML / Attributes)
    → Parser → Schema domain objects
    → Introspector (reads live DB via PDO) → Schema domain objects
    → SchemaDiffer → Operation[] (CreateTable, AddColumn, AlterColumn, …)
    → Platform (DB-specific SQL generation)
    → MigrationGenerator → PHP migration files (up/down)
    → MigrationRunner → executes + tracks in DB
```

### Key Modules

| Module | Role |
|---|---|
| `Cli/` | Entry point (`bin/rowcast-schema`), command dispatch, config loading |
| `Schema/` | Domain models: `Schema`, `Table`, `Column`, `Index`, `ForeignKey`, `ColumnType` enum |
| `Parser/` | Three parsers (`PhpSchemaParser`, `YamlSchemaParser`, `AttributeSchemaParser`) plus shared `ArraySchemaBuilder` |
| `Introspector/` | Driver-specific DB readers (MySQL, PostgreSQL, SQLite) via `IntrospectorFactory` |
| `Diff/` | `SchemaDiffer` computes `OperationInterface[]`; operations are platform-agnostic |
| `Platform/` | Driver-specific SQL generation (`MysqlPlatform`, `PostgresPlatform`, `SqlitePlatform`) via `PlatformFactory` |
| `TypeMapper/` | Bidirectional mapping between abstract `ColumnType` enum and native DB type strings |
| `Migration/` | `MigrationGenerator` (PHP file creation), `MigrationRunner`, `DatabaseMigrationRepository` |
| `SchemaBuilder/` | Fluent DSL (`SchemaBuilder` → `TableBuilder` → `ColumnBuilder`) used in generated migrations |
| `Attribute/` | PHP 8 attribute metadata: `#[Table]`, `#[Column]`, `#[Index]`, `#[ForeignKey]` |

### Important Design Details

- **Topological sort in `SchemaDiffer`**: `CreateTable`/`DropTable` operations are ordered to satisfy FK dependencies before execution.
- **SQLite rebuild pipeline** (`SqliteTableRebuilder`): SQLite's DDL limitations are worked around via create→copy→drop→rename.
- **Semantic FK diffing**: Foreign keys are compared by their logical properties (not name) to avoid spurious drop/add cycles — see recent fixes in git log.
- **Custom DB types**: Columns with unsupported types fall back to a raw type string (enables pgvector, citext, etc.).
- **No required external dependencies** except optional `symfony/yaml` for YAML schema support.

### Testing Pattern

Tests are pure unit tests — no live database. Domain objects (`Schema`, `Table`, `Column`) are constructed directly, passed to the system under test, and the resulting `Operation[]` or SQL strings are asserted. Mirror `src/` structure in `tests/`.
