<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Schema\Index;

final readonly class PostgresIndexLoader
{
    /**
     * @return array<string, array<string, Index>>
     */
    public function load(\PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT t.relname AS table_name,
                    i.relname AS index_name,
                    ix.indisunique AS is_unique,
                    a.attname AS column_name,
                    arr.ordinality AS column_position
             FROM pg_class t
             JOIN pg_namespace n ON n.oid = t.relnamespace
             JOIN pg_index ix ON t.oid = ix.indrelid
             JOIN pg_class i ON i.oid = ix.indexrelid
             JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS arr(attnum, ordinality) ON true
             JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = arr.attnum
             WHERE n.nspname = 'public'
               AND t.relkind = 'r'
               AND NOT ix.indisprimary
             ORDER BY t.relname, i.relname, arr.ordinality",
        );
        if ($stmt === false) {
            throw new \RuntimeException('Failed to introspect PostgreSQL indexes.');
        }

        /** @var array<string, array<string, list<string>>> $columnsByIndex */
        $columnsByIndex = [];
        /** @var array<string, array<string, bool>> $uniqueByIndex */
        $uniqueByIndex = [];

        /** @var array<string, mixed> $row */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $tableName = \is_string($row['table_name'] ?? null) ? $row['table_name'] : '';
            $indexName = \is_string($row['index_name'] ?? null) ? $row['index_name'] : '';
            $columnName = \is_string($row['column_name'] ?? null) ? $row['column_name'] : '';
            if ($tableName === '' || $indexName === '' || $columnName === '') {
                continue;
            }

            $columnsByIndex[$tableName] ??= [];
            $columnsByIndex[$tableName][$indexName] ??= [];
            $columnsByIndex[$tableName][$indexName][] = $columnName;

            $uniqueByIndex[$tableName] ??= [];
            $uniqueByIndex[$tableName][$indexName] = $this->toBool($row['is_unique'] ?? false);
        }

        $result = [];
        foreach ($columnsByIndex as $tableName => $indexes) {
            foreach ($indexes as $indexName => $columns) {
                $result[$tableName][$indexName] = new Index(
                    name: $indexName,
                    columns: $columns,
                    unique: $uniqueByIndex[$tableName][$indexName] ?? false,
                );
            }
        }

        return $result;
    }

    private function toBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $normalized = strtolower($value);
            return $normalized === 't' || $normalized === 'true' || $normalized === '1';
        }

        if (\is_int($value)) {
            return $value === 1;
        }

        return false;
    }
}
