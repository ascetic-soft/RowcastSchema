# CLAUDE.md

Primary repo instructions live in `AGENTS.md`. Keep this file aligned with it.

## Quick Checks

- `make install`
- `make ci` runs the same order as CI: `cs-check -> phpstan -> test`
- Single test file: `vendor/bin/phpunit tests/Path/To/SomeTest.php`

## Repo Facts

- This is a single-package Composer library; the CLI entrypoint is `bin/rowcast-schema`.
- CLI command wiring and parser selection live in `src/Cli/ApplicationContainer.php`.
- Default CLI config path is `rowcast-schema.php`; both `--config=...` and `--config ...` are supported.
- Config files may return either an array or a `Closure(string $cwd): array`.
- YAML schema support is optional and requires `symfony/yaml`.

## Easy-To-Miss Behavior

- `SchemaDiffer` topologically orders `CreateTable` and `DropTable` operations around foreign-key dependencies.
- If create-table cycles exist, `SchemaDiffer` extracts cyclic foreign keys into follow-up `AddForeignKey` operations.
- SQLite DDL rebuild logic lives in `src/Migration/SqliteTableRebuilder.php`; `MigrationRunner` routes `AlterColumn`, `DropColumn`, `AddForeignKey`, and `DropForeignKey` through it.
- Unknown database types are preserved as raw `databaseType` strings and emitted as-is.

## Tests

- Most tests are unit tests that construct schema/domain objects directly and assert operations or SQL.
- SQLite behavior has in-memory integration coverage in `tests/Integration/`; no external DB service is required.
