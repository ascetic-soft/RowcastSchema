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
        return sprintf('"%s"', str_replace('"', '""', $identifier));
    }

    protected function compileDropIndex(string $tableName, string $indexName): string
    {
        return sprintf('DROP INDEX %s', $this->quoteIdentifier($indexName));
    }

    protected function compileDropForeignKey(string $tableName, string $foreignKeyName): string
    {
        return sprintf(
            'ALTER TABLE %s DROP CONSTRAINT %s',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($foreignKeyName),
        );
    }

    protected function compileAlterColumn(AlterColumn $operation): array
    {
        $table = $this->quoteIdentifier($operation->tableName);
        $name = $this->quoteIdentifier($operation->newColumn->name);

        $statements = [
            sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE %s',
                $table,
                $name,
                $this->typeMapper->toSqlType($operation->newColumn),
            ),
        ];

        $statements[] = sprintf(
            'ALTER TABLE %s ALTER COLUMN %s %s NOT NULL',
            $table,
            $name,
            $operation->newColumn->nullable ? 'DROP' : 'SET',
        );

        if ($operation->newColumn->default !== null) {
            $default = strtoupper((string)$operation->newColumn->default) === 'CURRENT_TIMESTAMP'
                ? 'CURRENT_TIMESTAMP'
                : "'" . str_replace("'", "\\'", (string)$operation->newColumn->default) . "'";
            $statements[] = sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s', $table, $name, $default);
        } else {
            $statements[] = sprintf('ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT', $table, $name);
        }

        return $statements;
    }
}
