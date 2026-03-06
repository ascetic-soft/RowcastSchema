---
title: Schema Definition
layout: default
nav_order: 3
---

# Schema Definition
{: .no_toc }

Define your database structure in PHP or YAML.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Schema Formats

Rowcast Schema supports two formats. The parser is chosen automatically by the file extension configured in `rowcast-schema.php`.

### PHP format (default, no extra dependencies)

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

### YAML format (requires `symfony/yaml`)

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
YAML support is optional. Install it with `composer require symfony/yaml`. If the package is missing and you point to a `.yaml` file, the parser throws a clear error with installation instructions.

---

## Supported Abstract Types

| Type | Description |
|:-----|:-----------|
| `integer` | INT / INTEGER |
| `smallint` | SMALLINT |
| `bigint` | BIGINT |
| `string` | VARCHAR (default `length`: `255`) |
| `text` | TEXT |
| `boolean` | BOOLEAN / TINYINT(1) |
| `decimal` | DECIMAL (requires `precision`, `scale`) |
| `float` | FLOAT |
| `double` | DOUBLE |
| `datetime` | DATETIME |
| `date` | DATE |
| `time` | TIME |
| `timestamp` | TIMESTAMP |
| `uuid` | CHAR(36) / UUID |
| `json` | JSON / TEXT |
| `binary` | BLOB / BYTEA |
| `enum` | ENUM (requires `values`) |

---

## Column Properties

| Property | Type | Default | Description |
|:---------|:-----|:--------|:-----------|
| `type` | string | *required* | Abstract column type |
| `nullable` | bool | `false` | Allow NULL values |
| `default` | mixed | `null` | Default value (or `CURRENT_TIMESTAMP`) |
| `primaryKey` | bool | `false` | Shortcut for single-column PK |
| `autoIncrement` | bool | `false` | Auto-increment column |
| `length` | int | `255` for `string` | Length for `string` type |
| `precision` | int | `null` | Precision for `decimal` |
| `scale` | int | `null` | Scale for `decimal` |
| `unsigned` | bool | `false` | Unsigned integer |
| `comment` | string | `null` | Column comment |
| `values` | list | `[]` | Enum values for `enum` type |

---

## Table Properties

| Property | Type | Description |
|:---------|:-----|:-----------|
| `columns` | map | Column definitions (required, non-empty) |
| `primaryKey` | list | Composite primary key columns |
| `indexes` | map | Named indexes |
| `foreignKeys` | map | Foreign key definitions |
| `engine` | string | MySQL engine (e.g. `InnoDB`) |
| `charset` | string | MySQL charset |
| `collation` | string | MySQL collation |

---

## Indexes

```php
'indexes' => [
    'idx_users_email' => [
        'columns' => ['email'],
        'unique' => true,
    ],
    'idx_users_created' => [
        'columns' => ['created_at'],
    ],
],
```

| Property | Type | Default | Description |
|:---------|:-----|:--------|:-----------|
| `columns` | list | *required* | List of column names |
| `unique` | bool | `false` | Whether index is unique |

---

## Foreign Keys

```php
'foreignKeys' => [
    'fk_orders_user' => [
        'columns' => ['user_id'],
        'references' => [
            'table' => 'users',
            'columns' => ['id'],
        ],
        'onDelete' => 'cascade',
        'onUpdate' => 'restrict',
    ],
],
```

| Property | Type | Description |
|:---------|:-----|:-----------|
| `columns` | list | Local column names |
| `references.table` | string | Referenced table |
| `references.columns` | list | Referenced column names |
| `onDelete` | string | `cascade`, `restrict`, `set null`, `no action` |
| `onUpdate` | string | Same options as `onDelete` |

---

## Composite Primary Key

When a primary key spans multiple columns, use the table-level `primaryKey` property instead of per-column `primaryKey: true`:

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
