<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;

final readonly class SqliteTableRebuilder
{
    public function __construct(private SqliteTypeMapper $typeMapper = new SqliteTypeMapper())
    {
    }

    public function supports(OperationInterface $operation): bool
    {
        return $operation instanceof AlterColumn
            || $operation instanceof DropColumn
            || $operation instanceof AddForeignKey
            || $operation instanceof DropForeignKey;
    }

    public function execute(\PDO $pdo, OperationInterface $operation): void
    {
        if (!$this->supports($operation)) {
            throw new \LogicException('Operation is not supported by SQLite rebuilder.');
        }

        $tableName = match (true) {
            $operation instanceof AlterColumn => $operation->tableName,
            $operation instanceof DropColumn => $operation->tableName,
            $operation instanceof AddForeignKey => $operation->tableName,
            $operation instanceof DropForeignKey => $operation->tableName,
            default => throw new \LogicException('Unsupported operation type.'),
        };

        $state = $this->readState($pdo, $tableName);
        $columns = $state['columns'];
        $foreignKeys = $state['foreignKeys'];
        $indexes = $state['indexes'];

        if ($operation instanceof AlterColumn) {
            if (!isset($columns[$operation->oldColumn->name])) {
                throw new \RuntimeException(\sprintf('Column "%s.%s" does not exist.', $tableName, $operation->oldColumn->name));
            }
            $columns[$operation->newColumn->name] = $this->columnFromSchemaColumn($operation->newColumn);
            if ($operation->oldColumn->name !== $operation->newColumn->name) {
                unset($columns[$operation->oldColumn->name]);
                foreach ($indexes as &$index) {
                    $index['columns'] = array_map(
                        static fn (string $col): string => $col === $operation->oldColumn->name ? $operation->newColumn->name : $col,
                        $index['columns'],
                    );
                }
                unset($index);
                foreach ($foreignKeys as &$fk) {
                    $fk['columns'] = array_map(
                        static fn (string $col): string => $col === $operation->oldColumn->name ? $operation->newColumn->name : $col,
                        $fk['columns'],
                    );
                }
                unset($fk);
            }
        }

        if ($operation instanceof DropColumn) {
            unset($columns[$operation->columnName]);
            $indexes = array_values(array_filter(
                $indexes,
                static fn (array $index): bool => !\in_array($operation->columnName, $index['columns'], true),
            ));
            $foreignKeys = array_values(array_filter(
                $foreignKeys,
                static fn (array $fk): bool => !\in_array($operation->columnName, $fk['columns'], true),
            ));
        }

        if ($operation instanceof AddForeignKey) {
            $foreignKeys[] = $this->foreignKeyToArray($operation->foreignKey);
        }

        if ($operation instanceof DropForeignKey) {
            if ($operation->foreignKey === null) {
                if (\count($foreignKeys) === 1) {
                    $foreignKeys = [];
                } else {
                    throw new \RuntimeException(
                        \sprintf(
                            'SQLite drop foreign key "%s" requires foreign key metadata when table has multiple foreign keys.',
                            $operation->foreignKeyName,
                        ),
                    );
                }
            } else {
                $needle = $this->foreignKeyToArray($operation->foreignKey);
                $foreignKeys = array_values(array_filter(
                    $foreignKeys,
                    static fn (array $fk): bool => !(
                        $fk['referenceTable'] === $needle['referenceTable']
                            && $fk['columns'] === $needle['columns']
                            && $fk['referenceColumns'] === $needle['referenceColumns']
                    ),
                ));
            }
        }

        if ($columns === []) {
            throw new \RuntimeException(\sprintf('Cannot rebuild table "%s" without columns.', $tableName));
        }

        $this->rebuildTable($pdo, $tableName, $columns, $foreignKeys, $indexes, array_keys($state['columns']));
    }

    /**
     * @return array{
     *   columns: array<string, array{name: string, type: string, notnull: bool, default: mixed, pk: int}>,
     *   foreignKeys: list<array{
     *      name: string,
     *      columns: list<string>,
     *      referenceTable: string,
     *      referenceColumns: list<string>,
     *      onDelete: ?string,
     *      onUpdate: ?string
     *   }>,
     *   indexes: list<array{name: string, unique: bool, columns: list<string>}>
     * }
     */
    private function readState(\PDO $pdo, string $tableName): array
    {
        $quoted = str_replace("'", "''", $tableName);
        $columnsStmt = $pdo->query(\sprintf("PRAGMA table_info('%s')", $quoted));
        if ($columnsStmt === false) {
            throw new \RuntimeException(\sprintf('Failed to read SQLite table info for %s.', $tableName));
        }
        $columns = [];
        /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $row */
        foreach ($columnsStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $columns[$row['name']] = [
                'name' => $row['name'],
                'type' => $row['type'] !== '' ? $row['type'] : 'TEXT',
                'notnull' => (int)$row['notnull'] === 1,
                'default' => $row['dflt_value'],
                'pk' => (int)$row['pk'],
            ];
        }

        $fkStmt = $pdo->query(\sprintf("PRAGMA foreign_key_list('%s')", $quoted));
        if ($fkStmt === false) {
            throw new \RuntimeException(\sprintf('Failed to read SQLite foreign keys for %s.', $tableName));
        }
        $fkGroups = [];
        /** @var array{id: int, seq: int, table: string, from: string, to: string, on_update: string, on_delete: string} $fkRow */
        foreach ($fkStmt->fetchAll(\PDO::FETCH_ASSOC) as $fkRow) {
            $id = (int)$fkRow['id'];
            if (!isset($fkGroups[$id])) {
                $fkGroups[$id] = [
                    'name' => \sprintf('fk_%s_%d', $tableName, $id),
                    'columns' => [],
                    'referenceTable' => (string)$fkRow['table'],
                    'referenceColumns' => [],
                    'onDelete' => (string)$fkRow['on_delete'] !== 'NO ACTION' ? (string)$fkRow['on_delete'] : null,
                    'onUpdate' => (string)$fkRow['on_update'] !== 'NO ACTION' ? (string)$fkRow['on_update'] : null,
                ];
            }
            $fkGroups[$id]['columns'][] = (string)$fkRow['from'];
            $fkGroups[$id]['referenceColumns'][] = (string)$fkRow['to'];
        }

        $idxStmt = $pdo->query(\sprintf("PRAGMA index_list('%s')", $quoted));
        if ($idxStmt === false) {
            throw new \RuntimeException(\sprintf('Failed to read SQLite indexes for %s.', $tableName));
        }
        $indexes = [];
        /** @var array{name: string, unique: int, origin: string} $idx */
        foreach ($idxStmt->fetchAll(\PDO::FETCH_ASSOC) as $idx) {
            if ($idx['origin'] !== 'c') {
                continue;
            }
            $idxName = (string)$idx['name'];
            $idxInfoStmt = $pdo->query(\sprintf("PRAGMA index_info('%s')", str_replace("'", "''", $idxName)));
            if ($idxInfoStmt === false) {
                continue;
            }
            $idxColumns = [];
            /** @var array{name: string} $idxInfo */
            foreach ($idxInfoStmt->fetchAll(\PDO::FETCH_ASSOC) as $idxInfo) {
                $idxColumns[] = (string)$idxInfo['name'];
            }
            $indexes[] = [
                'name' => $idxName,
                'unique' => (int)$idx['unique'] === 1,
                'columns' => $idxColumns,
            ];
        }

        return [
            'columns' => $columns,
            'foreignKeys' => array_values($fkGroups),
            'indexes' => $indexes,
        ];
    }

    /**
     * @param array<string, array{name: string, type: string, notnull: bool, default: mixed, pk: int}> $columns
     * @param list<array{name: string, columns: list<string>, referenceTable: string, referenceColumns: list<string>, onDelete: ?string, onUpdate: ?string}> $foreignKeys
     * @param list<array{name: string, unique: bool, columns: list<string>}> $indexes
     * @param list<string> $oldColumnOrder
     */
    private function rebuildTable(
        \PDO $pdo,
        string $tableName,
        array $columns,
        array $foreignKeys,
        array $indexes,
        array $oldColumnOrder,
    ): void {
        $tmp = \sprintf('__rowcast_tmp_%s_%s', $tableName, substr(sha1((string)microtime(true)), 0, 8));
        $quotedTable = $this->quoteIdentifier($tableName);
        $quotedTmp = $this->quoteIdentifier($tmp);

        $pkMap = [];
        foreach ($columns as $column) {
            if ($column['pk'] > 0) {
                $pkMap[$column['pk']] = $column['name'];
            }
        }
        ksort($pkMap);
        $pkColumns = array_values($pkMap);

        $parts = [];
        foreach ($columns as $column) {
            $part = \sprintf('%s %s', $this->quoteIdentifier($column['name']), $column['type']);
            if ($column['notnull']) {
                $part .= ' NOT NULL';
            }
            if ($column['default'] !== null) {
                $part .= ' DEFAULT ' . $this->quoteRawDefault($column['default']);
            }
            $parts[] = $part;
        }

        if ($pkColumns !== []) {
            $parts[] = 'PRIMARY KEY (' . implode(', ', array_map([$this, 'quoteIdentifier'], $pkColumns)) . ')';
        }

        foreach ($foreignKeys as $fk) {
            $cols = implode(', ', array_map([$this, 'quoteIdentifier'], $fk['columns']));
            $refCols = implode(', ', array_map([$this, 'quoteIdentifier'], $fk['referenceColumns']));
            $fkSql = \sprintf(
                'FOREIGN KEY (%s) REFERENCES %s (%s)',
                $cols,
                $this->quoteIdentifier($fk['referenceTable']),
                $refCols,
            );
            if ($fk['onDelete'] !== null) {
                $fkSql .= ' ON DELETE ' . strtoupper($fk['onDelete']);
            }
            if ($fk['onUpdate'] !== null) {
                $fkSql .= ' ON UPDATE ' . strtoupper($fk['onUpdate']);
            }
            $parts[] = $fkSql;
        }

        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            $pdo->exec(\sprintf('CREATE TABLE %s (%s)', $quotedTmp, implode(', ', $parts)));

            $newColumns = array_keys($columns);
            $copyColumns = array_values(array_intersect($oldColumnOrder, $newColumns));
            if ($copyColumns !== []) {
                $quotedCopy = implode(', ', array_map([$this, 'quoteIdentifier'], $copyColumns));
                $pdo->exec(\sprintf('INSERT INTO %s (%s) SELECT %s FROM %s', $quotedTmp, $quotedCopy, $quotedCopy, $quotedTable));
            }

            $pdo->exec(\sprintf('DROP TABLE %s', $quotedTable));
            $pdo->exec(\sprintf('ALTER TABLE %s RENAME TO %s', $quotedTmp, $quotedTable));

            foreach ($indexes as $index) {
                if ($index['columns'] === []) {
                    continue;
                }
                // Recreate only indexes fully resolvable against new columns.
                if (array_diff($index['columns'], array_keys($columns)) !== []) {
                    continue;
                }
                $idxCols = implode(', ', array_map([$this, 'quoteIdentifier'], $index['columns']));
                $kind = $index['unique'] ? 'UNIQUE INDEX' : 'INDEX';
                $pdo->exec(\sprintf(
                    'CREATE %s %s ON %s (%s)',
                    $kind,
                    $this->quoteIdentifier($index['name']),
                    $quotedTable,
                    $idxCols,
                ));
            }
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * @return array{name: string, type: string, notnull: bool, default: mixed, pk: int}
     */
    private function columnFromSchemaColumn(Column $column): array
    {
        return [
            'name' => $column->name,
            'type' => $this->typeMapper->toSqlType($column),
            'notnull' => !$column->nullable,
            'default' => $column->default !== null ? $this->normalizeDefault($column->default) : null,
            'pk' => $column->primaryKey ? 1 : 0,
        ];
    }

    /**
     * @return array{name: string, columns: list<string>, referenceTable: string, referenceColumns: list<string>, onDelete: ?string, onUpdate: ?string}
     */
    private function foreignKeyToArray(ForeignKey $foreignKey): array
    {
        return [
            'name' => $foreignKey->name,
            'columns' => $foreignKey->columns,
            'referenceTable' => $foreignKey->referenceTable,
            'referenceColumns' => $foreignKey->referenceColumns,
            'onDelete' => $foreignKey->onDelete,
            'onUpdate' => $foreignKey->onUpdate,
        ];
    }

    private function normalizeDefault(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string)$value;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Default value must be scalar.');
        }
        if (strtoupper($value) === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function quoteRawDefault(mixed $default): string
    {
        if (\is_int($default) || \is_float($default)) {
            return (string)$default;
        }
        if (!\is_string($default)) {
            throw new \RuntimeException('Unsupported SQLite default value type during table rebuild.');
        }

        return $default;
    }
}
