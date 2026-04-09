<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

use AsceticSoft\RowcastSchema\Schema\ReferentialAction;

final readonly class SqliteTableStateReader
{
    /**
     * @return array{
     *   columns: array<string, array{name: string, type: string, notnull: bool, default: mixed, pk: int}>,
     *   foreignKeys: list<array{
     *      name: string,
     *      columns: list<string>,
     *      referenceTable: string,
     *      referenceColumns: list<string>,
     *      onDelete: ReferentialAction|string|null,
     *      onUpdate: ReferentialAction|string|null
     *   }>,
     *   indexes: list<array{name: string, unique: bool, columns: list<string>}>
     * }
     */
    public function read(\PDO $pdo, string $tableName): array
    {
        return [
            'columns' => $this->readColumns($pdo, $tableName),
            'foreignKeys' => $this->readForeignKeys($pdo, $tableName),
            'indexes' => $this->readIndexes($pdo, $tableName),
        ];
    }

    /**
     * @return array<string, array{name: string, type: string, notnull: bool, default: mixed, pk: int}>
     */
    private function readColumns(\PDO $pdo, string $tableName): array
    {
        $quoted = str_replace("'", "''", $tableName);
        $stmt = $pdo->query(\sprintf("PRAGMA table_info('%s')", $quoted));
        if ($stmt === false) {
            throw new \RuntimeException(\sprintf('Failed to read SQLite table info for %s.', $tableName));
        }

        $columns = [];
        /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $row */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $columns[$row['name']] = [
                'name' => $row['name'],
                'type' => $row['type'] !== '' ? $row['type'] : 'TEXT',
                'notnull' => (int) $row['notnull'] === 1,
                'default' => $row['dflt_value'],
                'pk' => (int) $row['pk'],
            ];
        }

        return $columns;
    }

    /**
     * @return list<array{name: string, columns: list<string>, referenceTable: string, referenceColumns: list<string>, onDelete: ReferentialAction|string|null, onUpdate: ReferentialAction|string|null}>
     */
    private function readForeignKeys(\PDO $pdo, string $tableName): array
    {
        $quoted = str_replace("'", "''", $tableName);
        $stmt = $pdo->query(\sprintf("PRAGMA foreign_key_list('%s')", $quoted));
        if ($stmt === false) {
            throw new \RuntimeException(\sprintf('Failed to read SQLite foreign keys for %s.', $tableName));
        }

        $fkGroups = [];
        /** @var array{id: int, seq: int, table: string, from: string, to: string, on_update: string, on_delete: string} $fkRow */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $fkRow) {
            $id = (int) $fkRow['id'];
            if (!isset($fkGroups[$id])) {
                $fkGroups[$id] = [
                    'name' => \sprintf('fk_%s_%d', $tableName, $id),
                    'columns' => [],
                    'referenceTable' => (string) $fkRow['table'],
                    'referenceColumns' => [],
                    'onDelete' => (string) $fkRow['on_delete'] !== 'NO ACTION'
                        ? ReferentialAction::tryFromString((string) $fkRow['on_delete'])
                        : null,
                    'onUpdate' => (string) $fkRow['on_update'] !== 'NO ACTION'
                        ? ReferentialAction::tryFromString((string) $fkRow['on_update'])
                        : null,
                ];
            }
            $fkGroups[$id]['columns'][] = (string) $fkRow['from'];
            $fkGroups[$id]['referenceColumns'][] = (string) $fkRow['to'];
        }

        return array_values($fkGroups);
    }

    /**
     * @return list<array{name: string, unique: bool, columns: list<string>}>
     */
    private function readIndexes(\PDO $pdo, string $tableName): array
    {
        $quoted = str_replace("'", "''", $tableName);
        $stmt = $pdo->query(\sprintf("PRAGMA index_list('%s')", $quoted));
        if ($stmt === false) {
            throw new \RuntimeException(\sprintf('Failed to read SQLite indexes for %s.', $tableName));
        }

        $indexes = [];
        /** @var array{name: string, unique: int, origin: string} $idx */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $idx) {
            if ($idx['origin'] !== 'c') {
                continue;
            }
            $idxName = (string) $idx['name'];
            $idxInfoStmt = $pdo->query(\sprintf("PRAGMA index_info('%s')", str_replace("'", "''", $idxName)));
            if ($idxInfoStmt === false) {
                continue;
            }
            $idxColumns = [];
            /** @var array{name: string} $idxInfo */
            foreach ($idxInfoStmt->fetchAll(\PDO::FETCH_ASSOC) as $idxInfo) {
                $idxColumns[] = (string) $idxInfo['name'];
            }
            $indexes[] = [
                'name' => $idxName,
                'unique' => (int) $idx['unique'] === 1,
                'columns' => $idxColumns,
            ];
        }

        return $indexes;
    }
}
