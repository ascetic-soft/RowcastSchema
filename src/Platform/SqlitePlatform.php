<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\ReferentialAction;
use AsceticSoft\RowcastSchema\Schema\Table;

final readonly class SqlitePlatform extends AbstractPlatform
{
    public function supportsDdlTransactions(): bool
    {
        return false;
    }

    public function toSql(OperationInterface $operation): array
    {
        if ($operation instanceof AlterColumn
            || $operation instanceof AddForeignKey
            || $operation instanceof DropForeignKey) {
            return [];
        }

        return parent::toSql($operation);
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return \sprintf('"%s"', str_replace('"', '""', $identifier));
    }

    protected function compileDropIndex(string $tableName, string $indexName): string
    {
        return \sprintf('DROP INDEX %s', $this->quoteIdentifier($indexName));
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

        foreach ($table->foreignKeys as $foreignKey) {
            $columns = implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $foreignKey->columns));
            $referenceColumns = implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $foreignKey->referenceColumns));
            $fkSql = \sprintf(
                'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
                $this->quoteIdentifier($foreignKey->name),
                $columns,
                $this->quoteIdentifier($foreignKey->referenceTable),
                $referenceColumns,
            );
            if ($foreignKey->onDelete !== null) {
                $fkSql .= ' ON DELETE ' . ReferentialAction::toSql($foreignKey->onDelete);
            }
            if ($foreignKey->onUpdate !== null) {
                $fkSql .= ' ON UPDATE ' . ReferentialAction::toSql($foreignKey->onUpdate);
            }
            $parts[] = $fkSql;
        }

        $statements = [
            \sprintf('CREATE TABLE %s (%s)', $this->quoteIdentifier($table->name), implode(', ', $parts)),
        ];
        foreach ($table->indexes as $index) {
            $statements[] = $this->compileAddIndex($table->name, $index);
        }

        return $statements;
    }
}
