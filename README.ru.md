# Rowcast Schema

Schema-first инструмент миграций для PDO-баз данных (PHP 8.4+).

Без внешних зависимостей. Описывайте структуру БД в PHP-файле, сравнивайте с реальной базой и генерируйте обратимые PHP-миграции автоматически. Спроектирован для работы вместе с [Rowcast](https://github.com/ascetic-soft/Rowcast).

English version: [README.md](README.md)

**Документация:** [English](https://ascetic-soft.github.io/RowcastSchema/) | [Русский](https://ascetic-soft.github.io/RowcastSchema/ru/)

## Требования

- PHP >= 8.4
- Расширение PDO

## Установка

```bash
composer require ascetic-soft/rowcast-schema
```

Опциональная поддержка YAML-схемы:

```bash
composer require symfony/yaml
```

## Быстрый старт

### 1. Создайте конфигурационный файл

`rowcast-schema.php` в корне проекта:

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
    'migration_table' => '_rowcast_migrations',
    'ignore_tables' => [
        '/^tmp_/',
        '/^audit_/',
        static fn (string $table): bool => str_ends_with($table, '_shadow'),
    ],
];
```

`migration_table` задаёт таблицу учёта применённых миграций.
Эта таблица всегда игнорируется автоматически при сравнении схемы.
Используйте `ignore_tables` для своих правил игнора: regex-строки и/или callback.

### 2. Опишите схему

`schema.php`:

```php
<?php

return [
    'tables' => [
        'users' => [
            'columns' => [
                'id' => ['type' => 'integer', 'primaryKey' => true, 'autoIncrement' => true],
                'email' => ['type' => 'string', 'length' => 255],
                'status' => ['type' => 'enum', 'values' => ['active', 'banned']],
                'created_at' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],
            ],
            'indexes' => [
                'idx_users_email' => ['columns' => ['email'], 'unique' => true],
            ],
        ],
    ],
];
```

### 3. Сгенерируйте и примените миграцию

```bash
# Сгенерировать миграцию из диффа схемы
vendor/bin/rowcast-schema diff

# Применить pending-миграции
vendor/bin/rowcast-schema migrate

# Проверить статус синхронизации
vendor/bin/rowcast-schema status
```

## Описание схемы

### Поддерживаемые форматы

| Формат | Файл | Зависимость |
|--------|------|-------------|
| PHP-массив (по умолчанию) | `schema.php` | Нет |
| YAML | `schema.yaml` / `schema.yml` | `symfony/yaml` |

Формат определяется автоматически по расширению файла.

### Абстрактные типы колонок

`integer`, `smallint`, `bigint`, `string`, `text`, `boolean`, `decimal`, `float`, `double`, `datetime`, `date`, `time`, `timestamp`, `uuid`, `json`, `binary`, `enum`

### Параметры колонки

| Параметр | По умолчанию | Описание |
|----------|--------------|----------|
| `type` | *(обязательно)* | Абстрактный тип колонки |
| `nullable` | `false` | Разрешить NULL |
| `default` | — | Значение по умолчанию |
| `primaryKey` | `false` | Первичный ключ |
| `autoIncrement` | `false` | Автоинкремент |
| `length` | — | Длина строки/бинарного поля |
| `precision` / `scale` | — | Точность decimal |
| `unsigned` | `false` | Беззнаковое целое |
| `comment` | — | Комментарий колонки |
| `values` | — | Список значений enum |

### Внешние ключи

```php
'foreignKeys' => [
    'fk_posts_user' => [
        'columns' => ['user_id'],
        'referenceTable' => 'users',
        'referenceColumns' => ['id'],
        'onDelete' => 'CASCADE',
        'onUpdate' => 'SET NULL',
    ],
],
```

## CLI-команды

```bash
vendor/bin/rowcast-schema <команда> [опции]
```

| Команда | Описание |
|---------|----------|
| `diff` | Сгенерировать миграцию из изменений схемы |
| `diff --dry-run` | Показать операции без генерации файла |
| `migrate` | Применить все pending-миграции |
| `rollback` | Откатить последнюю миграцию |
| `rollback --step=N` | Откатить последние N миграций |
| `status` | Показать состояние миграций и синхронизации |

## Как это работает

1. **Парсинг** — читает `schema.php` (или `.yaml`) и строит внутреннюю модель `Schema`.
2. **Интроспекция** — считывает текущую структуру БД через PDO.
3. **Дифф** — `SchemaDiffer` вычисляет список операций (create, drop, add, alter и др.).
4. **Генерация** — создаёт PHP-класс миграции с методами `up()` и `down()`.
5. **Выполнение** — `MigrationRunner` применяет операции через SQL-платформу, специфичную для БД.
6. **Трекинг** — применённые версии сохраняются в таблице `_rowcast_migrations`.

## Поддержка SQLite

SQLite ограниченно поддерживает DDL (`ALTER TABLE`, внешние ключи). Для неподдерживаемых операций используется **rebuild pipeline**:

1. Создаётся временная таблица с новой структурой
2. Данные копируются из оригинальной таблицы
3. Оригинал удаляется, временная таблица переименовывается
4. Пересоздаются индексы и внешние ключи

Это позволяет прозрачно выполнять сложные изменения схемы в SQLite.

## Архитектура

```
AsceticSoft\RowcastSchema\
├── Schema\
│   ├── Schema                        # Корневая модель схемы
│   ├── Table, Column, Index, ForeignKey  # Компоненты схемы
│   └── ColumnType                    # Enum абстрактных типов
├── Parser\
│   ├── SchemaParserInterface         # Контракт парсера
│   ├── PhpSchemaParser               # Парсер PHP-массивов (по умолчанию)
│   ├── YamlSchemaParser              # YAML-парсер (опционально)
│   └── ArraySchemaBuilder            # Общий билдер массив → Schema
├── Introspector\
│   ├── IntrospectorInterface         # Контракт интроспектора
│   ├── IntrospectorFactory           # PDO-драйвер → интроспектор
│   ├── MysqlIntrospector             # Интроспекция MySQL
│   ├── PostgresIntrospector          # Интроспекция PostgreSQL
│   └── SqliteIntrospector            # Интроспекция SQLite
├── Diff\
│   ├── SchemaDiffer                  # Движок сравнения схем
│   └── Operation\
│       ├── OperationInterface        # Контракт операции
│       ├── CreateTable, DropTable    # Операции над таблицами
│       ├── AddColumn, AlterColumn, DropColumn
│       ├── AddIndex, DropIndex
│       └── AddForeignKey, DropForeignKey
├── Platform\
│   ├── PlatformInterface             # Контракт генерации SQL
│   ├── PlatformFactory               # PDO-драйвер → платформа
│   ├── AbstractPlatform              # Общая SQL-логика
│   ├── MysqlPlatform                 # MySQL DDL
│   ├── PostgresPlatform              # PostgreSQL DDL
│   └── SqlitePlatform                # SQLite DDL (с rebuild)
├── Migration\
│   ├── MigrationInterface            # Контракт миграции
│   ├── AbstractMigration             # Базовый класс миграции
│   ├── MigrationGenerator            # Генератор PHP-файлов миграций
│   ├── MigrationLoader               # Загрузка миграций с диска
│   ├── MigrationRunner               # Применение/откат миграций
│   ├── MigrationRepositoryInterface  # Контракт репозитория
│   ├── DatabaseMigrationRepository   # Трекинг миграций в БД
│   └── SqliteTableRebuilder          # SQLite rebuild pipeline
├── SchemaBuilder\
│   ├── SchemaBuilder                 # Fluent API для операций миграции
│   ├── TableBuilder                  # Fluent-определение таблицы/колонок
│   └── ColumnBuilder                 # Fluent-свойства колонки
├── TypeMapper\
│   ├── TypeMapperInterface           # Контракт маппера типов
│   ├── MysqlTypeMapper               # Маппинг типов MySQL
│   ├── PostgresTypeMapper            # Маппинг типов PostgreSQL
│   └── SqliteTypeMapper              # Маппинг типов SQLite
├── Pdo\
│   └── PdoDriverResolver            # Централизованное определение PDO-драйвера
└── Cli\
    ├── Application                   # Точка входа CLI
    ├── Config                        # Загрузчик конфигурации
    └── Command\
        ├── CommandInterface          # Контракт команды
        ├── DiffCommand               # Команда diff
        ├── MigrateCommand            # Команда migrate
        ├── RollbackCommand           # Команда rollback
        └── StatusCommand             # Команда status
```

## Тестирование

```bash
composer install
vendor/bin/phpunit
```

Статический анализ:

```bash
vendor/bin/phpstan analyse
```

## Лицензия

MIT
