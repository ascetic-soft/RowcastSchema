<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Schema\Column;
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
                    tc.constraint_type
             FROM information_schema.columns c
             LEFT JOIN information_schema.key_column_usage kcu
                    ON c.table_name = kcu.table_name
                   AND c.column_name = kcu.column_name
                   AND c.table_schema = kcu.table_schema
             LEFT JOIN information_schema.table_constraints tc
                    ON tc.constraint_name = kcu.constraint_name
                   AND tc.table_schema = kcu.table_schema
             WHERE c.table_schema = 'public'
             ORDER BY c.table_name, c.ordinal_position",
        );
        if ($stmt === false) {
            throw new \RuntimeException('Failed to introspect PostgreSQL schema.');
        }

        $tables = [];
        /** @var array<string, mixed> $row */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $tableName = (string)$row['table_name'];
            $columnName = (string)$row['column_name'];
            $udtName = (string)$row['udt_name'];
            $isPrimary = (string)($row['constraint_type'] ?? '') === 'PRIMARY KEY';

            if (!isset($tables[$tableName])) {
                $tables[$tableName] = [
                    'columns' => [],
                    'pk' => [],
                ];
            }

            if ($isPrimary) {
                $tables[$tableName]['pk'][] = $columnName;
            }

            $tables[$tableName]['columns'][$columnName] = new Column(
                name: $columnName,
                type: $this->typeMapper->toAbstractType($udtName),
                nullable: (string)$row['is_nullable'] === 'YES',
                default: $row['column_default'],
                primaryKey: $isPrimary,
                autoIncrement: str_contains((string)$row['column_default'], 'nextval'),
            );
        }

        $result = [];
        foreach ($tables as $tableName => $data) {
            $result[$tableName] = new Table(
                name: $tableName,
                columns: $data['columns'],
                primaryKey: $data['pk'],
            );
        }

        return new Schema($result);
    }
}
