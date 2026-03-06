---
title: Главная
layout: default
nav_order: 1
parent: Русский
permalink: /ru/
---

# Rowcast Schema

{: .fs-9 }

Schema-first библиотека миграций для PDO, дружественная к Rowcast.
{: .fs-6 .fw-300 }

[![CI](https://github.com/ascetic-soft/RowcastSchema/actions/workflows/ci.yml/badge.svg)](https://github.com/ascetic-soft/RowcastSchema/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ascetic-soft/RowcastSchema/graph/badge.svg)](https://codecov.io/gh/ascetic-soft/RowcastSchema)
[![PHPStan Level 9](https://img.shields.io/badge/phpstan-level%209-brightgreen)](https://phpstan.org/)
[![Latest Stable Version](https://img.shields.io/packagist/v/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![PHP Version](https://img.shields.io/packagist/dependency-v/ascetic-soft/rowcast-schema/php)](https://packagist.org/packages/ascetic-soft/rowcast-schema)
[![License](https://img.shields.io/packagist/l/ascetic-soft/rowcast-schema)](https://packagist.org/packages/ascetic-soft/rowcast-schema)

[Быстрый старт]({{ '/ru/getting-started.html' | relative_url }}){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[English]({{ '/' | relative_url }}){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## Что такое Rowcast Schema?

Rowcast Schema — это **schema-first инструмент миграций** для PHP 8.4+. Опишите структуру базы данных в PHP или YAML файле, сравните с реальной БД и автоматически сгенерируйте PHP-миграции с методами `up()` и `down()`.

### Ключевые особенности

- **Schema-first** — описывайте таблицы, колонки, индексы и FK в `schema.php` или `schema.yaml`
- **Автоматический дифф** — сравнение схемы с реальной БД и генерация миграций
- **Нулевые обязательные зависимости** — только PHP 8.4 и ext-pdo; YAML опционален через `symfony/yaml`
- **Мультибаза** — MySQL, PostgreSQL и SQLite из коробки
- **Rebuild pipeline для SQLite** — автоматическая обработка сложных DDL-изменений
- **Fluent API миграций** — сгенерированные миграции используют удобный `SchemaBuilder`
- **PHPStan Level 9** — полностью статически проанализированная кодовая база
- **Дружественный к Rowcast** — общий PDO, те же соглашения

---

## Быстрый пример

Определите схему в `schema.php`:

```php
<?php

return [
    'tables' => [
        'users' => [
            'columns' => [
                'id' => ['type' => 'integer', 'primaryKey' => true, 'autoIncrement' => true],
                'email' => ['type' => 'string', 'length' => 255],
                'created_at' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],
            ],
            'indexes' => [
                'idx_users_email' => ['columns' => ['email'], 'unique' => true],
            ],
        ],
    ],
];
```

Затем сгенерируйте и примените миграции:

```bash
vendor/bin/rowcast-schema diff      # сгенерировать миграцию из изменений схемы
vendor/bin/rowcast-schema migrate   # применить pending-миграции
vendor/bin/rowcast-schema status    # проверить статус синхронизации
```

---

## Требования

- **PHP** >= 8.4
- Расширение **PDO**

## Установка

```bash
composer require ascetic-soft/rowcast-schema
```

---

## Документация

<div class="grid-container" markdown="0">
  <div class="grid-item">
    <h3><a href="{{ '/ru/getting-started.html' | relative_url }}">Быстрый старт</a></h3>
    <p>Установка, конфигурация и первая миграция за 5 минут.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/schema.html' | relative_url }}">Описание схемы</a></h3>
    <p>PHP и YAML форматы, типы колонок, индексы и FK.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/cli.html' | relative_url }}">CLI команды</a></h3>
    <p>diff, migrate, rollback и status.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/migrations.html' | relative_url }}">Миграции</a></h3>
    <p>Формат миграций, SchemaBuilder API и migration runner.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/sqlite.html' | relative_url }}">Поддержка SQLite</a></h3>
    <p>Rebuild pipeline для сложных DDL-изменений.</p>
  </div>
  <div class="grid-item">
    <h3><a href="{{ '/ru/api-reference.html' | relative_url }}">Справочник API</a></h3>
    <p>Полный справочник по классам и интерфейсам.</p>
  </div>
</div>
