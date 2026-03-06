---
title: SQLite Support
layout: default
nav_order: 6
---

# SQLite Support
{: .no_toc }

How Rowcast Schema handles SQLite's limited DDL capabilities.
{: .fs-6 .fw-300 }

<details open markdown="block">
  <summary>Table of contents</summary>
  {: .text-delta }
- TOC
{:toc}
</details>

---

## The Problem

SQLite has very limited `ALTER TABLE` support. You cannot:

- Modify a column type or constraints
- Drop a column (before SQLite 3.35.0)
- Add or drop foreign keys on an existing table

This makes schema migrations on SQLite significantly harder than on MySQL or PostgreSQL.

---

## The Rebuild Pipeline

Rowcast Schema solves this with an automatic **table rebuild pipeline**. When the migration runner encounters an unsupported DDL operation on SQLite, it delegates to `SqliteTableRebuilder`, which:

1. **Reads** the current table structure via `PRAGMA table_info`, `PRAGMA foreign_key_list`, and `PRAGMA index_list`.
2. **Creates** a temporary table with the new structure (including the requested change).
3. **Copies** data from the original table to the temporary table.
4. **Drops** the original table.
5. **Renames** the temporary table to the original name.
6. **Recreates** all indexes that are still valid for the new column set.

---

## Supported Rebuild Operations

| Operation | Description |
|:----------|:-----------|
| `AlterColumn` | Change column type, nullable, default, etc. |
| `DropColumn` | Remove a column (with data preservation for remaining columns) |
| `AddForeignKey` | Add a foreign key constraint |
| `DropForeignKey` | Remove a foreign key constraint |

---

## Foreign Key Safety

During a rebuild, foreign key checks are temporarily disabled:

```sql
PRAGMA foreign_keys = OFF;
-- ... rebuild ...
PRAGMA foreign_keys = ON;
```

This prevents constraint violations during the data copy phase.

---

## Transactional DDL on SQLite

{: .note }
`SqlitePlatform::supportsDdlTransactions()` returns `false`. SQLite does support transactions for DDL, but the rebuild pipeline manages its own safety via `PRAGMA foreign_keys` toggling rather than relying on transaction rollback.

---

## Limitations

- **Rename detection** — The differ does not detect renames, so a column rename generates a drop + create (data in the dropped column is lost). Edit the migration manually if you need a rename.
- **Complex defaults** — SQLite stores default values as-is in the schema. The rebuilder preserves them during copy but does not evaluate expressions.
