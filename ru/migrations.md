---
title: Миграции
layout: default
nav_order: 5
parent: Русский
---

# Миграции
{: .no_toc }

Формат сгенерированных миграций, SchemaBuilder API и migration runner.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Формат сгенерированной миграции

При запуске `rowcast-schema diff` генерируется PHP-класс:

```php
<?php

declare(strict_types=1);

use AsceticSoft\RowcastSchema\Migration\AbstractMigration;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;
use AsceticSoft\RowcastSchema\SchemaBuilder\TableBuilder;

final class Migration_20260306_143022_CreateUsersTable extends AbstractMigration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->createTable('users', function (TableBuilder $table) {
            $table->column('id', 'integer')->primaryKey()->autoIncrement();
            $table->column('email', 'string'); // длина по умолчанию: 255
            $table->column('created_at', ColumnType::Datetime)->default('CURRENT_TIMESTAMP');
        });
        $schema->addIndex('users', 'idx_users_email', ['email'], unique: true);
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropTable('users');
    }
}
```

### Именование файлов

```
Migration_YYYYMMDD_HHMMSS_Description.php
```

---

## MigrationInterface

Каждая миграция реализует:

```php
interface MigrationInterface
{
    public function up(SchemaBuilder $schema): void;
    public function down(SchemaBuilder $schema): void;
}
```

---

## SchemaBuilder API

`SchemaBuilder` — **коллектор операций**. Вызовы его методов не выполняют SQL — они накапливают `Operation[]`, которые `MigrationRunner` позже компилирует и выполняет.

### Операции с таблицами

| Метод | Описание |
|:------|:---------|
| `createTable(string $name, callable $callback)` | Создать таблицу |
| `dropTable(string $name)` | Удалить таблицу |
| `addColumn(string $table, Column $column)` | Добавить колонку |
| `dropColumn(string $table, string $column)` | Удалить колонку |
| `alterColumn(string $table, Column $old, Column $new)` | Изменить колонку |

### Операции с индексами

| Метод | Описание |
|:------|:---------|
| `addIndex(...)` | Создать индекс |
| `dropIndex(...)` | Удалить индекс |

### Операции с FK

| Метод | Описание |
|:------|:---------|
| `addForeignKey(...)` | Добавить FK |
| `dropForeignKey(...)` | Удалить FK |

---

## TableBuilder (Fluent API)

```php
$schema->createTable('products', function (TableBuilder $table) {
    $table->column('id', 'uuid')->primaryKey();
    $table->column('name', 'string'); // длина по умолчанию: 255
    $table->column('price', 'decimal')->precision(10, 2)->unsigned();
    $table->column('description', 'text')->nullable();
    $table->column('created_at', ColumnType::Datetime)->default('CURRENT_TIMESTAMP');
    $table->column('meta', 'jsonb'); // кастомный raw-тип БД
});
```

`column()` принимает:

- enum `ColumnType` (`ColumnType::String`, `ColumnType::Datetime`, ...),
- известные строковые абстрактные типы (`'string'`, `'integer'`, ...),
- любые кастомные raw-типы БД (`'jsonb'`, `'citext'`, `'numeric(20,6)'`, ...).

### Raw SQL

При необходимости можно выполнить произвольный SQL в методах миграции:

```php
public function up(SchemaBuilder $schema): void
{
    $schema->sql("UPDATE users SET status = 'active' WHERE status IS NULL");
}
```

---

## Migration Runner

`MigrationRunner` оркестрирует выполнение:

1. Загружает файлы миграций через `MigrationLoader`.
2. Проверяет состояние через `MigrationRepositoryInterface` (таблица `_rowcast_migrations`).
3. Выполняет `up()` или `down()`.
4. Компилирует операции через SQL-платформу.
5. Оборачивает в транзакцию если `supportsDdlTransactions()` (PostgreSQL).

---

## Таблица состояния

Применённые миграции хранятся в `_rowcast_migrations`:

| Колонка | Тип | Описание |
|:--------|:----|:---------|
| `version` | VARCHAR(255) PK | Имя класса миграции |
| `applied_at` | DATETIME | Когда применена |

---

## Операции переименования

{: .important }
Переименования **не** детектируются автоматически. Дифф не может отличить rename от drop + create. Если переименовали колонку в схеме — сгенерируется drop + create. Отредактируйте миграцию вручную при необходимости.
