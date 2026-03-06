---
title: API Reference
layout: default
nav_order: 7
---

# API Reference
{: .no_toc }

Complete reference for all public classes and interfaces.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Schema Model

### `Schema`

```php
final readonly class Schema
{
    /** @var array<string, Table> */
    public array $tables;

    public function hasTable(string $name): bool;
    public function getTable(string $name): ?Table;
}
```

### `Table`

```php
final readonly class Table
{
    public string $name;
    /** @var array<string, Column> */
    public array $columns;
    /** @var list<string> */
    public array $primaryKey;
    /** @var array<string, Index> */
    public array $indexes;
    /** @var array<string, ForeignKey> */
    public array $foreignKeys;
    public ?string $engine;
    public ?string $charset;
    public ?string $collation;

    public function hasColumn(string $name): bool;
    public function getColumn(string $name): ?Column;
}
```

### `Column`

```php
final readonly class Column
{
    public string $name;
    public ColumnType $type;
    public bool $nullable;
    public mixed $default;
    public bool $primaryKey;
    public bool $autoIncrement;
    public ?int $length;
    public ?int $precision;
    public ?int $scale;
    public bool $unsigned;
    public ?string $comment;
    /** @var list<string> */
    public array $enumValues;
}
```

### `ColumnType` (enum)

```php
enum ColumnType: string
{
    case Integer = 'integer';
    case Smallint = 'smallint';
    case Bigint = 'bigint';
    case String = 'string';
    case Text = 'text';
    case Boolean = 'boolean';
    case Decimal = 'decimal';
    case Float = 'float';
    case Double = 'double';
    case Datetime = 'datetime';
    case Date = 'date';
    case Time = 'time';
    case Timestamp = 'timestamp';
    case Uuid = 'uuid';
    case Json = 'json';
    case Binary = 'binary';
    case Enum = 'enum';
}
```

### `Index`

```php
final readonly class Index
{
    public string $name;
    /** @var list<string> */
    public array $columns;
    public bool $unique;
}
```

### `ForeignKey`

```php
final readonly class ForeignKey
{
    public string $name;
    /** @var list<string> */
    public array $columns;
    public string $referenceTable;
    /** @var list<string> */
    public array $referenceColumns;
    public ?string $onDelete;
    public ?string $onUpdate;
}
```

---

## Parser

### `SchemaParserInterface`

```php
interface SchemaParserInterface
{
    public function parse(string $path): Schema;
}
```

### `PhpSchemaParser`

Default parser. Reads a PHP file that returns an array.

### `YamlSchemaParser`

YAML parser. Requires `symfony/yaml`. Throws `RuntimeException` if the package is missing.

### `ArraySchemaBuilder`

Shared service used by both parsers. Converts a raw array into a `Schema` model.

```php
final class ArraySchemaBuilder
{
    /** @param array<mixed, mixed> $parsed */
    public function build(array $parsed): Schema;
}
```

---

## Diff

### `SchemaDiffer`

```php
final class SchemaDiffer
{
    /** @return list<OperationInterface> */
    public function diff(Schema $from, Schema $to): array;
}
```

### Operations

All operations implement `OperationInterface` (marker interface):

| Class | Key Properties |
|:------|:--------------|
| `CreateTable` | `Table $table` |
| `DropTable` | `string $tableName` |
| `AddColumn` | `string $tableName`, `Column $column` |
| `DropColumn` | `string $tableName`, `string $columnName` |
| `AlterColumn` | `string $tableName`, `Column $oldColumn`, `Column $newColumn` |
| `AddIndex` | `string $tableName`, `Index $index` |
| `DropIndex` | `string $tableName`, `string $indexName` |
| `AddForeignKey` | `string $tableName`, `ForeignKey $foreignKey` |
| `DropForeignKey` | `string $tableName`, `string $foreignKeyName`, `?ForeignKey $foreignKey` |

---

## Platform

### `PlatformInterface`

```php
interface PlatformInterface
{
    /** @return list<string> */
    public function toSql(OperationInterface $operation): array;
    public function supportsDdlTransactions(): bool;
}
```

### Implementations

| Class | Driver |
|:------|:-------|
| `MysqlPlatform` | `mysql` |
| `PostgresPlatform` | `pgsql` |
| `SqlitePlatform` | `sqlite` |

### `PlatformFactory`

```php
final class PlatformFactory
{
    public function createForPdo(\PDO $pdo): PlatformInterface;
}
```

---

## Introspector

### `IntrospectorInterface`

```php
interface IntrospectorInterface
{
    public function introspect(\PDO $pdo): Schema;
}
```

### `IntrospectorFactory`

```php
final class IntrospectorFactory
{
    public function createForPdo(\PDO $pdo): IntrospectorInterface;
}
```

---

## Type Mapper

### `TypeMapperInterface`

```php
interface TypeMapperInterface
{
    public function toSqlType(Column $column): string;
    public function toAbstractType(string $sqlType): ColumnType;
}
```

---

## Migration

### `MigrationInterface`

```php
interface MigrationInterface
{
    public function up(SchemaBuilder $schema): void;
    public function down(SchemaBuilder $schema): void;
}
```

### `MigrationRepositoryInterface`

```php
interface MigrationRepositoryInterface
{
    /** @return list<string> */
    public function getApplied(): array;
    public function markApplied(string $version): void;
    public function markRolledBack(string $version): void;
    public function ensureTable(): void;
}
```

### `MigrationRunner`

Orchestrates loading, applying, and rolling back migrations using `MigrationLoader`, `MigrationRepositoryInterface`, and `PlatformInterface`.

### `MigrationGenerator`

Generates PHP migration class files from `Operation[]`.

---

## Pdo

### `PdoDriverResolver`

```php
final class PdoDriverResolver
{
    public function resolve(\PDO $pdo): string;
}
```

Shared service used by `IntrospectorFactory` and `PlatformFactory` to detect the PDO driver name.
