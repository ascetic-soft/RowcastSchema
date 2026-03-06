<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Table;

final readonly class SqlitePlatform extends AbstractPlatform
{
    public function supportsDdlTransactions(): bool
    {
        return false;
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return \sprintf('"%s"', str_replace('"', '""', $identifier));
    }

    protected function compileDropIndex(string $tableName, string $indexName): string
    {
        return \sprintf('DROP INDEX %s', $this->quoteIdentifier($indexName));
    }

    protected function compileAddForeignKey(string $tableName, ForeignKey $foreignKey): string
    {
        throw new \RuntimeException(
            \sprintf(
                'SQLite cannot add foreign key "%s" to existing table "%s" without table rebuild.',
                $foreignKey->name,
                $tableName,
            ),
        );
    }

    protected function compileDropForeignKey(string $tableName, string $foreignKeyName): string
    {
        throw new \RuntimeException(
            \sprintf(
                'SQLite cannot drop foreign key "%s" from table "%s" without table rebuild.',
                $foreignKeyName,
                $tableName,
            ),
        );
    }

    protected function compileAlterColumn(AlterColumn $operation): array
    {
        throw new \RuntimeException(
            \sprintf(
                'SQLite cannot alter column "%s.%s" without table rebuild pipeline.',
                $operation->tableName,
                $operation->newColumn->name,
            ),
        );
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
                $fkSql .= ' ON DELETE ' . strtoupper($foreignKey->onDelete);
            }
            if ($foreignKey->onUpdate !== null) {
                $fkSql .= ' ON UPDATE ' . strtoupper($foreignKey->onUpdate);
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
