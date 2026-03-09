---
title: Описание схемы
layout: default
nav_order: 3
parent: Русский
---

# Описание схемы
{: .no_toc }

Определите структуру БД в PHP или YAML.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Форматы схемы

Rowcast Schema поддерживает три формата. Парсер выбирается автоматически по пути в `schema`:

- путь к директории -> парсер атрибутов
- файл `.php` -> PHP-парсер
- файл `.yaml` / `.yml` -> YAML-парсер

### PHP-формат (по умолчанию, без зависимостей)

```php
<?php

return [
    'tables' => [
        'users' => [
            'columns' => [
                'id' => ['type' => 'integer', 'primaryKey' => true, 'autoIncrement' => true],
                'email' => ['type' => 'string', 'length' => 255],
            ],
            'indexes' => [
                'idx_users_email' => ['columns' => ['email'], 'unique' => true],
            ],
        ],
    ],
];
```

### YAML-формат (требует `symfony/yaml`)

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
    indexes:
      idx_users_email:
        columns: [email]
        unique: true
```

{: .note }
YAML-поддержка опциональна. Установите `composer require symfony/yaml`. Если пакет отсутствует и указан `.yaml`-файл, парсер выбросит понятную ошибку с инструкцией по установке.

### Формат с атрибутами (директория с PHP-классами)

```php
// rowcast-schema.php
return [
    'connection' => [
        'dsn' => 'mysql:host=localhost;dbname=app',
        'username' => 'root',
        'password' => 'secret',
    ],
    'schema' => __DIR__ . '/src/Entity',
    'migrations' => __DIR__ . '/migrations',
];
```

```php
use AsceticSoft\RowcastSchema\Attribute\Column;
use AsceticSoft\RowcastSchema\Attribute\Table;

#[Table]
final class User
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $email;
}
```

---

## Поддерживаемые абстрактные типы

| Тип | Описание |
|:----|:---------|
| `integer` | INT / INTEGER |
| `smallint` | SMALLINT |
| `bigint` | BIGINT |
| `string` | VARCHAR (длина по умолчанию: `255`) |
| `text` | TEXT |
| `boolean` | BOOLEAN / TINYINT(1) |
| `decimal` | DECIMAL (требует `precision`, `scale`) |
| `float` | FLOAT |
| `double` | DOUBLE |
| `datetime` | DATETIME |
| `date` | DATE |
| `time` | TIME |
| `timestamp` | TIMESTAMP |
| `timestamptz` | TIMESTAMPTZ |
| `uuid` | CHAR(36) / UUID |
| `json` | JSON / TEXT |
| `binary` | BLOB / BYTEA |
| `enum` | ENUM (требует `values`) |

---

## Кастомные типы БД

Можно передавать vendor-specific типы как строку в `type`:

```php
'columns' => [
    'embedding' => ['type' => 'vector(1536)', 'nullable' => true],
    'title_ci' => ['type' => 'citext'],
],
```

Неизвестные типы сохраняются как custom `databaseType` и попадают в SQL без изменений. Это работает для pgvector, citext, PostGIS и других специфичных типов.

---

## Параметры колонки

| Параметр | Тип | По умолч. | Описание |
|:---------|:----|:----------|:---------|
| `type` | string | *обязат.* | Абстрактный тип |
| `nullable` | bool | `false` | Разрешить NULL |
| `default` | mixed | `null` | Значение по умолчанию |
| `primaryKey` | bool | `false` | Первичный ключ |
| `autoIncrement` | bool | `false` | Автоинкремент |
| `length` | int | `255` для `string` | Длина для `string` |
| `precision` | int | `null` | Точность для `decimal` |
| `scale` | int | `null` | Масштаб для `decimal` |
| `unsigned` | bool | `false` | Беззнаковое целое |
| `comment` | string | `null` | Комментарий |
| `values` | list | `[]` | Значения для `enum` |

---

## Параметры таблицы

| Параметр | Тип | Описание |
|:---------|:----|:---------|
| `columns` | map | Определения колонок (обязательно) |
| `primaryKey` | list | Составной первичный ключ |
| `indexes` | map | Именованные индексы |
| `foreignKeys` | map | Внешние ключи |
| `engine` | string | MySQL engine (напр. `InnoDB`) |
| `charset` | string | MySQL charset |
| `collation` | string | MySQL collation |

---

## Индексы

```php
'indexes' => [
    'idx_users_email' => [
        'columns' => ['email'],
        'unique' => true,
    ],
],
```

---

## Внешние ключи

```php
'foreignKeys' => [
    'fk_orders_user' => [
        'columns' => ['user_id'],
        'references' => [
            'table' => 'users',
            'columns' => ['id'],
        ],
        'onDelete' => 'cascade',
    ],
],
```

---

## Составной первичный ключ

```php
'order_items' => [
    'columns' => [
        'order_id' => ['type' => 'integer'],
        'position' => ['type' => 'integer'],
        'product_id' => ['type' => 'integer'],
    ],
    'primaryKey' => ['order_id', 'position'],
],
```
