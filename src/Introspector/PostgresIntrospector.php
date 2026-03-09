<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use AsceticSoft\RowcastSchema\TypeMapper\TypeMapperInterface;

final readonly class PostgresIntrospector implements IntrospectorInterface
{
    public function __construct(private TypeMapperInterface $typeMapper)
    {
    }

    public function introspect(\PDO $pdo): Schema
    {
        $stmt = $pdo->query(
            "SELECT c.table_name, c.column_name, c.udt_name, c.is_nullable, c.column_default,
                    c.character_maximum_length, c.numeric_precision, c.numeric_scale,
                    pg_catalog.format_type(a.atttypid, a.atttypmod) AS formatted_type
             FROM information_schema.columns c
             JOIN pg_catalog.pg_class t
               ON t.relname = c.table_name
             JOIN pg_catalog.pg_namespace n
               ON n.oid = t.relnamespace
              AND n.nspname = c.table_schema
             JOIN pg_catalog.pg_attribute a
               ON a.attrelid = t.oid
              AND a.attname = c.column_name
              AND a.attnum > 0
              AND NOT a.attisdropped
             WHERE c.table_schema = 'public'
               AND t.relkind = 'r'
             ORDER BY c.table_name, c.ordinal_position",
        );
        if ($stmt === false) {
            throw new \RuntimeException('Failed to introspect PostgreSQL schema.');
        }

        $primaryKeys = $this->loadPrimaryKeys($pdo);
        $indexes = $this->loadIndexes($pdo);
        $foreignKeys = $this->loadForeignKeys($pdo);

        $tables = [];
        /** @var array<string, mixed> $row */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $tableName = \is_string($row['table_name'] ?? null) ? $row['table_name'] : '';
            $columnName = \is_string($row['column_name'] ?? null) ? $row['column_name'] : '';
            $udtName = \is_string($row['udt_name'] ?? null) ? $row['udt_name'] : '';
            $formattedType = \is_string($row['formatted_type'] ?? null) ? $row['formatted_type'] : $udtName;
            $isNullable = \is_string($row['is_nullable'] ?? null) ? $row['is_nullable'] : 'NO';
            $columnDefaultRaw = \is_string($row['column_default'] ?? null) ? $row['column_default'] : null;
            if ($tableName === '' || $columnName === '' || $udtName === '') {
                continue;
            }
            $isPrimary = \in_array($columnName, $primaryKeys[$tableName] ?? [], true);

            if (!isset($tables[$tableName])) {
                $tables[$tableName] = [
                    'columns' => [],
                ];
            }

            $abstractType = $this->typeMapper->toAbstractType($formattedType);
            $databaseType = null;
            if ($abstractType === null) {
                $abstractType = ColumnType::Text;
                $databaseType = $formattedType;
            }
            $length = $abstractType === ColumnType::String ? $this->toNullableInt($row['character_maximum_length'] ?? null) : null;
            $precision = $abstractType === ColumnType::Decimal ? $this->toNullableInt($row['numeric_precision'] ?? null) : null;
            $scale = $abstractType === ColumnType::Decimal ? $this->toNullableInt($row['numeric_scale'] ?? null) : null;
            $columnDefault = $this->normalizeDefault($columnDefaultRaw, $abstractType);

            $tables[$tableName]['columns'][$columnName] = new Column(
                name: $columnName,
                type: $abstractType,
                nullable: $isNullable === 'YES',
                default: $columnDefault,
                primaryKey: $isPrimary,
                autoIncrement: \is_string($columnDefault) && str_contains($columnDefault, 'nextval'),
                length: $length,
                precision: $precision,
                scale: $scale,
                databaseType: $databaseType,
            );
        }

        $result = [];
        foreach ($tables as $tableName => $data) {
            $result[$tableName] = new Table(
                name: $tableName,
                columns: $data['columns'],
                primaryKey: $primaryKeys[$tableName] ?? [],
                indexes: $indexes[$tableName] ?? [],
                foreignKeys: $foreignKeys[$tableName] ?? [],
            );
        }

        return new Schema($result);
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadPrimaryKeys(\PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT tc.table_name, kcu.column_name, kcu.ordinal_position
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
              AND tc.table_name = kcu.table_name
             WHERE tc.table_schema = 'public'
               AND tc.constraint_type = 'PRIMARY KEY'
             ORDER BY tc.table_name, kcu.ordinal_position",
        );
        if ($stmt === false) {
            throw new \RuntimeException('Failed to introspect PostgreSQL primary keys.');
        }

        $primaryKeys = [];
        /** @var array<string, mixed> $row */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $tableName = \is_string($row['table_name'] ?? null) ? $row['table_name'] : '';
            $columnName = \is_string($row['column_name'] ?? null) ? $row['column_name'] : '';
            if ($tableName === '' || $columnName === '') {
                continue;
            }

            $primaryKeys[$tableName] ??= [];
            $primaryKeys[$tableName][] = $columnName;
        }

        return $primaryKeys;
    }

    /**
     * @return array<string, array<string, Index>>
     */
    private function loadIndexes(\PDO $pdo): array
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

    /**
     * @return array<string, array<string, ForeignKey>>
     */
    private function loadForeignKeys(\PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT tc.table_name,
                    tc.constraint_name,
                    kcu.column_name,
                    ccu.table_name AS reference_table_name,
                    ccu.column_name AS reference_column_name,
                    rc.delete_rule,
                    rc.update_rule,
                    kcu.ordinal_position
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
              AND tc.table_name = kcu.table_name
             JOIN information_schema.constraint_column_usage ccu
               ON ccu.constraint_name = tc.constraint_name
              AND ccu.constraint_schema = tc.table_schema
             JOIN information_schema.referential_constraints rc
               ON rc.constraint_name = tc.constraint_name
              AND rc.constraint_schema = tc.table_schema
             WHERE tc.table_schema = 'public'
               AND tc.constraint_type = 'FOREIGN KEY'
             ORDER BY tc.table_name, tc.constraint_name, kcu.ordinal_position",
        );
        if ($stmt === false) {
            throw new \RuntimeException('Failed to introspect PostgreSQL foreign keys.');
        }

        /** @var array<string, array<string, array{
         *     columns: list<string>,
         *     reference_table: string,
         *     reference_columns: list<string>,
         *     on_delete: ?string,
         *     on_update: ?string
         * }>> $fks
         */
        $fks = [];

        /** @var array<string, mixed> $row */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $tableName = \is_string($row['table_name'] ?? null) ? $row['table_name'] : '';
            $fkName = \is_string($row['constraint_name'] ?? null) ? $row['constraint_name'] : '';
            $columnName = \is_string($row['column_name'] ?? null) ? $row['column_name'] : '';
            $referenceTable = \is_string($row['reference_table_name'] ?? null) ? $row['reference_table_name'] : '';
            $referenceColumn = \is_string($row['reference_column_name'] ?? null) ? $row['reference_column_name'] : '';
            if ($tableName === '' || $fkName === '' || $columnName === '' || $referenceTable === '' || $referenceColumn === '') {
                continue;
            }

            $fks[$tableName] ??= [];
            $fks[$tableName][$fkName] ??= [
                'columns' => [],
                'reference_table' => $referenceTable,
                'reference_columns' => [],
                'on_delete' => $this->normalizeReferentialRule($row['delete_rule'] ?? null),
                'on_update' => $this->normalizeReferentialRule($row['update_rule'] ?? null),
            ];

            $fks[$tableName][$fkName]['columns'][] = $columnName;
            $fks[$tableName][$fkName]['reference_columns'][] = $referenceColumn;
        }

        $result = [];
        foreach ($fks as $tableName => $definitions) {
            foreach ($definitions as $fkName => $definition) {
                $result[$tableName][$fkName] = new ForeignKey(
                    name: $fkName,
                    columns: $definition['columns'],
                    referenceTable: $definition['reference_table'],
                    referenceColumns: $definition['reference_columns'],
                    onDelete: $definition['on_delete'],
                    onUpdate: $definition['on_update'],
                );
            }
        }

        return $result;
    }

    private function normalizeDefault(?string $columnDefault, ColumnType $type): mixed
    {
        if ($columnDefault === null) {
            return null;
        }

        $default = trim($columnDefault);
        if ($default === '' || str_starts_with(strtoupper($default), 'NULL::')) {
            return null;
        }

        if (preg_match("/^'(.*)'::/s", $default, $matches) === 1) {
            $default = str_replace("''", "'", $matches[1]);
        }

        return match ($type) {
            ColumnType::Boolean => $this->normalizeBooleanDefault($default),
            ColumnType::Integer, ColumnType::Smallint, ColumnType::Bigint => is_numeric($default) ? (int) $default : $default,
            ColumnType::Decimal, ColumnType::Float, ColumnType::Double => is_numeric($default) ? (float) $default : $default,
            default => $default,
        };
    }

    private function normalizeBooleanDefault(string $default): string|bool
    {
        $normalized = strtolower(trim($default, "'"));

        return match ($normalized) {
            'true', 't', '1' => true,
            'false', 'f', '0' => false,
            default => $default,
        };
    }

    private function normalizeReferentialRule(mixed $rule): ?string
    {
        if (!\is_string($rule) || $rule === '' || $rule === 'NO ACTION') {
            return null;
        }

        return $rule;
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

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
