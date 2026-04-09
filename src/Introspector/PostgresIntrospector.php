<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use AsceticSoft\RowcastSchema\TypeMapper\TypeMapperInterface;

final readonly class PostgresIntrospector implements IntrospectorInterface
{
    public function __construct(
        private TypeMapperInterface $typeMapper,
        private PostgresIndexLoader $indexLoader = new PostgresIndexLoader(),
        private PostgresForeignKeyLoader $foreignKeyLoader = new PostgresForeignKeyLoader(),
    ) {
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
        $indexes = $this->indexLoader->load($pdo);
        $foreignKeys = $this->foreignKeyLoader->load($pdo);

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
                $tables[$tableName] = ['columns' => []];
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
