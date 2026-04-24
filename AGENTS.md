# AGENTS.md

## Quick Checks

- Install deps: `make install`
- Full local gate matches CI order: `make ci` (`cs-check -> phpstan -> test`)
- Focused checks:
  - `make cs-fix`
  - `make cs-check`
  - `make phpstan`
  - `make test`
  - Single test file: `vendor/bin/phpunit tests/Path/To/SomeTest.php`

## Code Standards

- PHP 8.4+ only.
- Every PHP file uses `declare(strict_types=1);`.
- PHPStan runs at level 9 on `src/`.
- CS Fixer enforces PSR-12, short arrays, and PHP 8.4 migration rules on both `src/` and `tests/`.

## Repo Shape

- This is a single-package Composer library, not a monorepo.
- CLI entrypoint is `bin/rowcast-schema`, which boots `AsceticSoft\RowcastSchema\Cli\Application`.
- CLI command wiring lives in `src/Cli/ApplicationContainer.php`.
- Main pipeline is: parser -> introspector -> `Diff\SchemaDiffer` -> platform SQL generation -> migration generation / runner.

## Behavior That Is Easy To Miss

- Default CLI config path is `rowcast-schema.php` in the current working directory; `--config=...` and `--config ...` are both supported.
- Config files may return either an array or a `Closure(string $cwd): array`.
- Parser selection is path-driven in `ApplicationContainer::createParser()`:
  - directory => attribute parser
  - `.php` => PHP schema parser
  - `.yaml` / `.yml` => YAML parser
- YAML schema support is optional; it requires `symfony/yaml` but is only listed under Composer `suggest`.
- `migration_table` defaults to `_rowcast_migrations`.
- `ignore_tables` accepts regex strings and callables; regexes are validated when config loads.

## Architecture Notes

- `SchemaDiffer` does more than a naive diff: it topologically orders `CreateTable` and `DropTable` operations around foreign-key dependencies.
- If create-table cycles exist, `SchemaDiffer` extracts cyclic foreign keys into follow-up `AddForeignKey` operations instead of failing.
- Custom database types are first-class: unknown types are preserved as raw `databaseType` strings and emitted as-is (tests cover examples like `citext` and `vector(1536)`).
- SQLite DDL is special-cased in `src/Migration/SqliteTableRebuilder.php`; `MigrationRunner` routes `AlterColumn`, `DropColumn`, `AddForeignKey`, and `DropForeignKey` through rebuild logic instead of normal platform SQL.

## Tests

- Most tests are pure unit tests that build schema/domain objects directly and assert `Operation[]` or SQL output.
- SQLite behavior has in-memory integration coverage in `tests/Integration/`; no external DB service is required for the test suite.
- Mirror `src/` structure when adding tests.
