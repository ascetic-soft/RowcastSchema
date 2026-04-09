<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

use AsceticSoft\RowcastSchema\Schema\ReferentialAction;

final readonly class SqliteTableRecreator
{
    /**
     * @param array<string, array{name: string, type: string, notnull: bool, default: mixed, pk: int}> $columns
     * @param list<array{name: string, columns: list<string>, referenceTable: string, referenceColumns: list<string>, onDelete: ReferentialAction|string|null, onUpdate: ReferentialAction|string|null}> $foreignKeys
     * @param list<array{name: string, unique: bool, columns: list<string>}> $indexes
     * @param list<string> $oldColumnOrder
     */
    public function rebuild(
        \PDO $pdo,
        string $tableName,
        array $columns,
        array $foreignKeys,
        array $indexes,
        array $oldColumnOrder,
    ): void {
        $tmp = \sprintf('__rowcast_tmp_%s_%s', $tableName, substr(sha1((string) microtime(true)), 0, 8));
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
                $fkSql .= ' ON DELETE ' . ReferentialAction::toSql($fk['onDelete']);
            }
            if ($fk['onUpdate'] !== null) {
                $fkSql .= ' ON UPDATE ' . ReferentialAction::toSql($fk['onUpdate']);
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

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function quoteRawDefault(mixed $default): string
    {
        if (\is_int($default) || \is_float($default)) {
            return (string) $default;
        }
        if (!\is_string($default)) {
            throw new \RuntimeException('Unsupported SQLite default value type during table rebuild.');
        }

        return $default;
    }
}
