# Rowcast Schema

`ascetic-soft/rowcast-schema` — schema-first библиотека миграций для PDO, дружественная к Rowcast.

English version: [README.md](README.md)

## Что делает библиотека

Базовый workflow:
- описываете структуру БД в `schema.yaml`;
- сравниваете схему с реальной БД (`diff`);
- автоматически генерируете PHP-миграции;
- применяете или откатываете миграции;
- проверяете статус синхронизации схемы и БД (`status`).

## Возможности

- YAML-описание схемы (`tables`, `columns`, `indexes`, `foreignKeys`)
- интроспекция текущей структуры БД через PDO
- дифф `schema.yaml` и реальной структуры БД
- генерация PHP-миграций (`up`/`down`)
- хранение состояния миграций в `_rowcast_migrations`
- поддержка MySQL, PostgreSQL и SQLite
- rebuild pipeline для SQLite в неподдерживаемых DDL-кейсах

## Установка

```bash
composer require ascetic-soft/rowcast-schema
```

## Конфигурация

Создайте `rowcast-schema.php` в корне проекта:

```php
<?php

return [
    'connection' => [
        'dsn' => 'mysql:host=localhost;dbname=app',
        'username' => 'root',
        'password' => 'secret',
        // 'options' => [],
    ],
    'schema' => __DIR__ . '/schema.yaml',
    'migrations' => __DIR__ . '/migrations',
];
```

## Формат `schema.yaml`

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

### Поддерживаемые абстрактные типы

- `integer`, `smallint`, `bigint`
- `string`, `text`
- `boolean`
- `decimal`, `float`, `double`
- `datetime`, `date`, `time`, `timestamp`
- `uuid`, `json`, `binary`, `enum`

### Параметры колонки

- `type` (обязательно)
- `nullable` (по умолчанию `false`)
- `default`
- `primaryKey`
- `autoIncrement`
- `length`, `precision`, `scale`
- `unsigned`
- `comment`
- `values` (для `enum`)

## CLI команды

Точка входа:

```bash
vendor/bin/rowcast-schema
```

### Diff

Сгенерировать миграцию из изменений схемы:

```bash
vendor/bin/rowcast-schema diff
```

Показать только операции без генерации файла:

```bash
vendor/bin/rowcast-schema diff --dry-run
```

### Migrate

Применить pending-миграции:

```bash
vendor/bin/rowcast-schema migrate
```

### Rollback

Откатить последнюю миграцию:

```bash
vendor/bin/rowcast-schema rollback
```

Откатить последние N миграций:

```bash
vendor/bin/rowcast-schema rollback --step=3
```

### Status

Показать состояние миграций и синхронизации схемы:

```bash
vendor/bin/rowcast-schema status
```

## Как это работает

1. Парсер читает `schema.yaml` и строит внутреннюю модель схемы.
2. Интроспектор считывает текущую структуру из БД.
3. `SchemaDiffer` вычисляет список операций (`create`, `drop`, `add`, `alter` и др.).
4. Генератор создаёт PHP-файл миграции.
5. `MigrationRunner` выполняет операции через SQL-платформу.
6. Применённые версии записываются в `_rowcast_migrations`.

## Нюансы SQLite

SQLite ограниченно поддерживает DDL (`ALTER TABLE`, операции с FK).  
Для неподдерживаемых случаев используется rebuild pipeline:
- создаётся временная таблица с новой структурой;
- данные копируются;
- таблицы меняются местами;
- индексы и FK пересоздаются.

Это позволяет автоматически выполнять сложные изменения схемы в SQLite.

## Статус проекта

Проект в активной разработке. API может расширяться в следующих версиях.
