<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;

final readonly class PostgresPlatform extends AbstractPlatform
{
    public function supportsDdlTransactions(): bool
    {
        return true;
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return \sprintf('"%s"', str_replace('"', '""', $identifier));
    }

    protected function compileDropIndex(string $tableName, string $indexName): string
    {
        return \sprintf('DROP INDEX %s', $this->quoteIdentifier($indexName));
    }

    protected function compileDropForeignKey(string $tableName, string $foreignKeyName): string
    {
        return \sprintf(
            'ALTER TABLE %s DROP CONSTRAINT %s',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($foreignKeyName),
        );
    }

    protected function compileAlterColumn(AlterColumn $operation): array
    {
        $table = $this->quoteIdentifier($operation->tableName);
        $oldName = $this->quoteIdentifier($operation->columnName);
        $newName = $this->quoteIdentifier($operation->newColumn->name);

        $statements = [];
        if ($operation->columnName !== $operation->newColumn->name) {
            $statements[] = \sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $table, $oldName, $newName);
        }

        $targetName = $newName;

        if ($operation->oldColumn === null) {
            $statements[] = \sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE %s',
                $table,
                $targetName,
                $this->typeMapper->toSqlType($operation->newColumn),
            );
            $statements[] = \sprintf(
                'ALTER TABLE %s ALTER COLUMN %s %s NOT NULL',
                $table,
                $targetName,
                $operation->newColumn->nullable ? 'DROP' : 'SET',
            );

            if ($operation->newColumn->default !== null) {
                $default = $this->compileDefaultValue($operation->newColumn->default);
                $statements[] = \sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s', $table, $targetName, $default);
            } else {
                $statements[] = \sprintf('ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT', $table, $targetName);
            }

            return $statements;
        }

        $oldType = $this->typeMapper->toSqlType($operation->oldColumn);
        $newType = $this->typeMapper->toSqlType($operation->newColumn);
        if ($oldType !== $newType) {
            $statements[] = \sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE %s',
                $table,
                $targetName,
                $newType,
            );
        }

        if ($operation->oldColumn->nullable !== $operation->newColumn->nullable) {
            $statements[] = \sprintf(
                'ALTER TABLE %s ALTER COLUMN %s %s NOT NULL',
                $table,
                $targetName,
                $operation->newColumn->nullable ? 'DROP' : 'SET',
            );
        }

        if ($operation->oldColumn->default !== $operation->newColumn->default) {
            if ($operation->newColumn->default !== null) {
                $default = $this->compileDefaultValue($operation->newColumn->default);
                $statements[] = \sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s', $table, $targetName, $default);
            } else {
                $statements[] = \sprintf('ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT', $table, $targetName);
            }
        }

        return $statements;
    }

    private function compileDefaultValue(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string)$value;
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
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
