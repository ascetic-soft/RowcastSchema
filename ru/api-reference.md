---
title: Справочник API
layout: default
nav_order: 7
parent: Русский
---

# Справочник API
{: .no_toc }

Полный справочник по публичным классам и интерфейсам.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Модель схемы

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

Значения: `integer`, `smallint`, `bigint`, `string`, `text`, `boolean`, `decimal`, `float`, `double`, `datetime`, `date`, `time`, `timestamp`, `uuid`, `json`, `binary`, `enum`.

---

## Парсер

### `SchemaParserInterface`

```php
interface SchemaParserInterface
{
    public function parse(string $path): Schema;
}
```

Реализации: `PhpSchemaParser` (по умолчанию), `YamlSchemaParser` (требует `symfony/yaml`).

---

## Дифф

### `SchemaDiffer`

```php
final class SchemaDiffer
{
    /** @return list<OperationInterface> */
    public function diff(Schema $from, Schema $to): array;
}
```

### Операции

`CreateTable`, `DropTable`, `AddColumn`, `DropColumn`, `AlterColumn`, `AddIndex`, `DropIndex`, `AddForeignKey`, `DropForeignKey`.

---

## Платформа

### `PlatformInterface`

```php
interface PlatformInterface
{
    /** @return list<string> */
    public function toSql(OperationInterface $operation): array;
    public function supportsDdlTransactions(): bool;
}
```

Реализации: `MysqlPlatform`, `PostgresPlatform`, `SqlitePlatform`.

---

## Интроспектор

### `IntrospectorInterface`

```php
interface IntrospectorInterface
{
    public function introspect(\PDO $pdo): Schema;
}
```

---

## Маппер типов

### `TypeMapperInterface`

```php
interface TypeMapperInterface
{
    public function toSqlType(Column $column): string;
    public function toAbstractType(string $sqlType): ColumnType;
}
```

---

## Миграции

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

---

## Pdo

### `PdoDriverResolver`

```php
final class PdoDriverResolver
{
    public function resolve(\PDO $pdo): string;
}
```

Общий сервис для определения драйвера PDO, используемый в `IntrospectorFactory` и `PlatformFactory`.
