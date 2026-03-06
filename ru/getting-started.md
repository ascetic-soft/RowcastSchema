---
title: Быстрый старт
layout: default
nav_order: 2
parent: Русский
---

# Быстрый старт
{: .no_toc }

Начните работу с Rowcast Schema за 5 минут.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Установка

```bash
composer require ascetic-soft/rowcast-schema
```

**Требования:**
- PHP >= 8.4
- Расширение PDO

### Опционально: поддержка YAML

Если предпочитаете YAML-формат:

```bash
composer require symfony/yaml
```

---

## Конфигурация

Создайте `rowcast-schema.php` в корне проекта:

```php
<?php

return [
    'connection' => [
        'dsn' => 'mysql:host=localhost;dbname=app',
        'username' => 'root',
        'password' => 'secret',
    ],
    'schema' => __DIR__ . '/schema.php',
    'migrations' => __DIR__ . '/migrations',
];
```

| Ключ | Описание |
|:-----|:---------|
| `connection.dsn` | Строка подключения PDO |
| `connection.username` | Имя пользователя БД |
| `connection.password` | Пароль |
| `connection.options` | Опциональный массив PDO-опций |
| `schema` | Путь к файлу схемы (`.php`, `.yaml`, `.yml`) |
| `migrations` | Директория для миграций |

---

## Определите схему

Создайте `schema.php`:

```php
<?php

return [
    'tables' => [
        'users' => [
            'columns' => [
                'id' => [
                    'type' => 'integer',
                    'primaryKey' => true,
                    'autoIncrement' => true,
                ],
                'email' => [
                    'type' => 'string',
                    'length' => 255,
                ],
                'created_at' => [
                    'type' => 'datetime',
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ],
            'indexes' => [
                'idx_users_email' => [
                    'columns' => ['email'],
                    'unique' => true,
                ],
            ],
        ],
    ],
];
```

---

## Сгенерируйте первую миграцию

```bash
vendor/bin/rowcast-schema diff
```

Эта команда сравнивает схему с текущей БД и создаёт PHP-файл миграции в директории `migrations/`.

Предварительный просмотр без генерации файла:

```bash
vendor/bin/rowcast-schema diff --dry-run
```

---

## Примените миграции

```bash
vendor/bin/rowcast-schema migrate
```

---

## Проверьте статус

```bash
vendor/bin/rowcast-schema status
```

Показывает применённые и pending-миграции, а также расхождения между схемой и БД.

---

## Что дальше?

- [Описание схемы]({{ '/ru/schema.html' | relative_url }}) — все типы колонок, индексы и FK
- [CLI команды]({{ '/ru/cli.html' | relative_url }}) — полный справочник CLI
- [Миграции]({{ '/ru/migrations.html' | relative_url }}) — формат миграций и SchemaBuilder API
- [Поддержка SQLite]({{ '/ru/sqlite.html' | relative_url }}) — rebuild pipeline
- [Справочник API]({{ '/ru/api-reference.html' | relative_url }}) — справочник по классам
