---
title: CLI команды
layout: default
nav_order: 4
parent: Русский
---

# CLI команды
{: .no_toc }

Все доступные команды `vendor/bin/rowcast-schema`.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Содержание</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## Обзор

CLI читает конфигурацию из `rowcast-schema.php` в текущей рабочей директории.

```bash
vendor/bin/rowcast-schema <команда> [опции]
```

---

## diff

Сравнить файл схемы с реальной БД и сгенерировать миграцию.

```bash
vendor/bin/rowcast-schema diff
```

### Опции

| Опция | Описание |
|:------|:---------|
| `--dry-run` | Вывести операции без генерации файла |

### Как это работает

1. Парсит файл схемы → строит желаемую модель `Schema`.
2. Интроспектирует БД через PDO → строит текущую модель `Schema`.
3. `SchemaDiffer` вычисляет список операций.
4. `MigrationGenerator` создаёт PHP-класс миграции.

---

## migrate

Применить все pending-миграции в порядке timestamp.

```bash
vendor/bin/rowcast-schema migrate
```

---

## rollback

Откатить последнюю миграцию или несколько.

```bash
# Откатить последнюю
vendor/bin/rowcast-schema rollback

# Откатить последние 3
vendor/bin/rowcast-schema rollback --step=3
```

### Опции

| Опция | Описание |
|:------|:---------|
| `--step=N` | Количество миграций для отката (по умолчанию: 1) |

---

## status

Показать состояние миграций и синхронизации схемы.

```bash
vendor/bin/rowcast-schema status
```

### Выводит

- Список применённых миграций
- Список pending-миграций
- Live-дифф между файлом схемы и текущей БД
- Сводку: «Schema is in sync» или список расхождений
