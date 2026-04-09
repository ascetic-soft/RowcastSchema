<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Platform\DefaultValueFormatter;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;

final readonly class SqliteTableRebuilder
{
    public function __construct(
        private SqliteTypeMapper $typeMapper = new SqliteTypeMapper(),
        private SqliteTableStateReader $stateReader = new SqliteTableStateReader(),
        private SqliteTableRecreator $recreator = new SqliteTableRecreator(),
    ) {
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
            $operation instanceof AlterColumn,
            $operation instanceof DropColumn,
            $operation instanceof AddForeignKey,
            $operation instanceof DropForeignKey => $operation->tableName,
            default => throw new \LogicException('Unsupported operation type.'),
        };

        $state = $this->stateReader->read($pdo, $tableName);
        $columns = $state['columns'];
        $foreignKeys = $state['foreignKeys'];
        $indexes = $state['indexes'];

        if ($operation instanceof AlterColumn) {
            if (!isset($columns[$operation->columnName])) {
                throw new \RuntimeException(\sprintf('Column "%s.%s" does not exist.', $tableName, $operation->columnName));
            }
            $columns[$operation->newColumn->name] = $this->columnFromSchemaColumn($operation->newColumn);
            if ($operation->columnName !== $operation->newColumn->name) {
                unset($columns[$operation->columnName]);
                foreach ($indexes as &$index) {
                    $index['columns'] = array_map(
                        static fn (string $col): string => $col === $operation->columnName ? $operation->newColumn->name : $col,
                        $index['columns'],
                    );
                }
                unset($index);
                foreach ($foreignKeys as &$fk) {
                    $fk['columns'] = array_map(
                        static fn (string $col): string => $col === $operation->columnName ? $operation->newColumn->name : $col,
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

        $this->recreator->rebuild($pdo, $tableName, $columns, $foreignKeys, $indexes, array_keys($state['columns']));
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
            'default' => $column->default !== null ? DefaultValueFormatter::formatSqlite($column->default) : null,
            'pk' => $column->primaryKey ? 1 : 0,
        ];
    }

    /**
     * @return array{name: string, columns: list<string>, referenceTable: string, referenceColumns: list<string>, onDelete: \AsceticSoft\RowcastSchema\Schema\ReferentialAction|string|null, onUpdate: \AsceticSoft\RowcastSchema\Schema\ReferentialAction|string|null}
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
}
