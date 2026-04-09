<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\ReferentialAction;

final readonly class PostgresForeignKeyLoader
{
    /**
     * @return array<string, array<string, ForeignKey>>
     */
    public function load(\PDO $pdo): array
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
         *     on_delete: ReferentialAction|string|null,
         *     on_update: ReferentialAction|string|null
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

    private function normalizeReferentialRule(mixed $rule): ReferentialAction|string|null
    {
        if (!\is_string($rule) || $rule === '' || $rule === 'NO ACTION') {
            return null;
        }

        return ReferentialAction::tryFromString($rule);
    }
}
