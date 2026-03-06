<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\Table;
use AsceticSoft\RowcastSchema\TypeMapper\TypeMapperInterface;

abstract readonly class AbstractPlatform implements PlatformInterface
{
    public function __construct(protected TypeMapperInterface $typeMapper)
    {
    }

    public function toSql(OperationInterface $operation): array
    {
        return match (true) {
            $operation instanceof CreateTable => $this->compileCreateTable($operation->table),
            $operation instanceof DropTable => [\sprintf('DROP TABLE %s', $this->quoteIdentifier($operation->tableName))],
            $operation instanceof AddColumn => [\sprintf(
                'ALTER TABLE %s ADD COLUMN %s',
                $this->quoteIdentifier($operation->tableName),
                $this->compileColumnDefinition($operation->column),
            )],
            $operation instanceof DropColumn => [\sprintf(
                'ALTER TABLE %s DROP COLUMN %s',
                $this->quoteIdentifier($operation->tableName),
                $this->quoteIdentifier($operation->columnName),
            )],
            $operation instanceof AlterColumn => $this->compileAlterColumn($operation),
            $operation instanceof AddIndex => [$this->compileAddIndex($operation->tableName, $operation->index)],
            $operation instanceof DropIndex => [$this->compileDropIndex($operation->tableName, $operation->indexName)],
            $operation instanceof AddForeignKey => [$this->compileAddForeignKey($operation->tableName, $operation->foreignKey)],
            $operation instanceof DropForeignKey => [$this->compileDropForeignKey($operation->tableName, $operation->foreignKeyName)],
            default => throw new \LogicException('Unsupported migration operation.'),
        };
    }

    /**
     * @return list<string>
     */
    protected function compileCreateTable(Table $table): array
    {
        $parts = [];
        foreach ($table->columns as $column) {
            $parts[] = $this->compileColumnDefinition($column);
        }

        if ($table->primaryKey !== []) {
            $parts[] = \sprintf(
                'PRIMARY KEY (%s)',
                implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $table->primaryKey)),
            );
        }

        $sql = \sprintf(
            'CREATE TABLE %s (%s)',
            $this->quoteIdentifier($table->name),
            implode(', ', $parts),
        );

        $statements = [$sql];
        foreach ($table->indexes as $index) {
            $statements[] = $this->compileAddIndex($table->name, $index);
        }
        foreach ($table->foreignKeys as $foreignKey) {
            $statements[] = $this->compileAddForeignKey($table->name, $foreignKey);
        }

        return $statements;
    }

    protected function compileColumnDefinition(Column $column): string
    {
        $sql = \sprintf('%s %s', $this->quoteIdentifier($column->name), $this->typeMapper->toSqlType($column));
        if ($column->unsigned) {
            $sql .= ' UNSIGNED';
        }
        if (!$column->nullable) {
            $sql .= ' NOT NULL';
        }
        if ($column->default !== null) {
            $sql .= \sprintf(' DEFAULT %s', $this->compileDefaultValue($column->default));
        }
        if ($column->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        return $sql;
    }

    protected function compileAddIndex(string $tableName, Index $index): string
    {
        $kind = $index->unique ? 'UNIQUE INDEX' : 'INDEX';
        $columns = implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $index->columns));

        return \sprintf(
            'CREATE %s %s ON %s (%s)',
            $kind,
            $this->quoteIdentifier($index->name),
            $this->quoteIdentifier($tableName),
            $columns,
        );
    }

    protected function compileDropIndex(string $tableName, string $indexName): string
    {
        return \sprintf('DROP INDEX %s ON %s', $this->quoteIdentifier($indexName), $this->quoteIdentifier($tableName));
    }

    protected function compileAddForeignKey(string $tableName, ForeignKey $foreignKey): string
    {
        $columns = implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $foreignKey->columns));
        $referenceColumns = implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $foreignKey->referenceColumns));

        $sql = \sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($foreignKey->name),
            $columns,
            $this->quoteIdentifier($foreignKey->referenceTable),
            $referenceColumns,
        );

        if ($foreignKey->onDelete !== null) {
            $sql .= ' ON DELETE ' . strtoupper($foreignKey->onDelete);
        }
        if ($foreignKey->onUpdate !== null) {
            $sql .= ' ON UPDATE ' . strtoupper($foreignKey->onUpdate);
        }

        return $sql;
    }

    protected function compileDropForeignKey(string $tableName, string $foreignKeyName): string
    {
        return \sprintf(
            'ALTER TABLE %s DROP FOREIGN KEY %s',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($foreignKeyName),
        );
    }

    /**
     * @return list<string>
     */
    protected function compileAlterColumn(AlterColumn $operation): array
    {
        if ($operation->columnName !== $operation->newColumn->name) {
            return [
                \sprintf(
                    'ALTER TABLE %s CHANGE COLUMN %s %s',
                    $this->quoteIdentifier($operation->tableName),
                    $this->quoteIdentifier($operation->columnName),
                    $this->compileColumnDefinition($operation->newColumn),
                ),
            ];
        }

        return [
            \sprintf(
                'ALTER TABLE %s MODIFY COLUMN %s',
                $this->quoteIdentifier($operation->tableName),
                $this->compileColumnDefinition($operation->newColumn),
            ),
        ];
    }

    abstract protected function quoteIdentifier(string $identifier): string;

    private function compileDefaultValue(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string)$value;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Default column value must be scalar.');
        }
        if (strtoupper($value) === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }

        return "'" . str_replace("'", "\\'", $value) . "'";
    }
}
