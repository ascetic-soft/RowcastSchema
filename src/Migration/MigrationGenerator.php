<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

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
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final class MigrationGenerator
{
    /**
     * @param list<OperationInterface> $operations
     */
    public function generate(array $operations, string $migrationsPath): string
    {
        $timestamp = date('Ymd_His');
        $className = 'Migration_' . $timestamp;
        $filePath = rtrim($migrationsPath, '/\\') . '/' . $className . '.php';

        $body = $this->buildBody($className, $operations);

        if (!is_dir($migrationsPath) && !mkdir($migrationsPath, 0o777, true) && !is_dir($migrationsPath)) {
            throw new \RuntimeException(\sprintf('Unable to create migrations directory: %s', $migrationsPath));
        }

        file_put_contents($filePath, $body);

        return $filePath;
    }

    /**
     * @param list<OperationInterface> $operations
     */
    private function buildBody(string $className, array $operations): string
    {
        $upLines = [];
        foreach ($operations as $operation) {
            $upLines = [...$upLines, ...$this->operationToBuilderLines($operation, reverse: false)];
        }

        $downLines = [];
        $reversed = array_reverse($operations);
        foreach ($reversed as $operation) {
            $downLines = [...$downLines, ...$this->operationToBuilderLines($operation, reverse: true)];
        }

        $upBody = $upLines !== []
            ? implode("\n", array_map(static fn (string $line): string => '        ' . $line, $upLines))
            : "        // No schema changes detected.\n";
        $downBody = $downLines !== []
            ? implode("\n", array_map(static fn (string $line): string => '        ' . $line, $downLines))
            : "        // No rollback operations generated.\n";

        return <<<PHP
            <?php

            declare(strict_types=1);

            use AsceticSoft\\RowcastSchema\\Migration\\AbstractMigration;
            use AsceticSoft\\RowcastSchema\\Schema\\Column;
            use AsceticSoft\\RowcastSchema\\Schema\\ColumnType;
            use AsceticSoft\\RowcastSchema\\SchemaBuilder\\SchemaBuilder;
            use AsceticSoft\\RowcastSchema\\SchemaBuilder\\TableBuilder;

            final class $className extends AbstractMigration
            {
                public function up(SchemaBuilder \$schema): void
                {
            {$upBody}
                }

                public function down(SchemaBuilder \$schema): void
                {
            {$downBody}
                }
            }

            PHP;
    }

    /**
     * @return list<string>
     */
    private function operationToBuilderLines(OperationInterface $operation, bool $reverse): array
    {
        return match (true) {
            $operation instanceof CreateTable => $reverse
                ? [\sprintf("\$schema->dropTable('%s');", $operation->table->name)]
                : $this->createTableLines($operation),
            $operation instanceof DropTable => $reverse
                ? [\sprintf('// TODO: recreate dropped table %s manually for rollback.', $operation->tableName)]
                : [\sprintf("\$schema->dropTable('%s');", $operation->tableName)],
            $operation instanceof AddColumn => $reverse
                ? [\sprintf("\$schema->dropColumn('%s', '%s');", $operation->tableName, $operation->column->name)]
                : [\sprintf("\$schema->addColumn('%s', %s);", $operation->tableName, $this->columnExpression($operation->column))],
            $operation instanceof DropColumn => $reverse
                ? [\sprintf('// TODO: restore dropped column %s.%s manually for rollback.', $operation->tableName, $operation->columnName)]
                : [\sprintf("\$schema->dropColumn('%s', '%s');", $operation->tableName, $operation->columnName)],
            $operation instanceof AlterColumn => $this->alterColumnLines($operation, $reverse),
            $operation instanceof AddIndex => [
                $reverse
                    ? \sprintf("\$schema->dropIndex('%s', '%s');", $operation->tableName, $operation->index->name)
                    : \sprintf(
                        "\$schema->addIndex('%s', '%s', %s, %s);",
                        $operation->tableName,
                        $operation->index->name,
                        $this->exportValue($operation->index->columns),
                        $operation->index->unique ? 'true' : 'false',
                    ),
            ],
            $operation instanceof DropIndex => $reverse
                ? [\sprintf('// TODO: restore dropped index %s on %s manually for rollback.', $operation->indexName, $operation->tableName)]
                : [\sprintf("\$schema->dropIndex('%s', '%s');", $operation->tableName, $operation->indexName)],
            $operation instanceof AddForeignKey => [
                $reverse
                    ? \sprintf("\$schema->dropForeignKey('%s', '%s');", $operation->tableName, $operation->foreignKey->name)
                    : \sprintf(
                        "\$schema->addForeignKey('%s', '%s', %s, '%s', %s, %s, %s);",
                        $operation->tableName,
                        $operation->foreignKey->name,
                        $this->exportValue($operation->foreignKey->columns),
                        $operation->foreignKey->referenceTable,
                        $this->exportValue($operation->foreignKey->referenceColumns),
                        $operation->foreignKey->onDelete !== null ? "'" . $operation->foreignKey->onDelete . "'" : 'null',
                        $operation->foreignKey->onUpdate !== null ? "'" . $operation->foreignKey->onUpdate . "'" : 'null',
                    ),
            ],
            $operation instanceof DropForeignKey => $reverse
                ? [\sprintf('// TODO: restore dropped foreign key %s on %s manually for rollback.', $operation->foreignKeyName, $operation->tableName)]
                : [\sprintf("\$schema->dropForeignKey('%s', '%s');", $operation->tableName, $operation->foreignKeyName)],
            default => ['// Unsupported operation in generator.'],
        };
    }

    /**
     * @return list<string>
     */
    private function createTableLines(CreateTable $operation): array
    {
        $lines = [
            \sprintf("\$schema->createTable('%s', function (TableBuilder \$table): void {", $operation->table->name),
        ];

        foreach ($operation->table->columns as $column) {
            $lines[] = '    ' . $this->columnBuilderLine($column);
        }

        if ($operation->table->primaryKey !== []) {
            $lines[] = \sprintf('    $table->primaryKey(%s);', $this->exportValue($operation->table->primaryKey));
        }

        foreach ($operation->table->indexes as $index) {
            $lines[] = \sprintf(
                '    $table->index(%s, %s, %s);',
                $this->exportValue($index->name),
                $this->exportValue($index->columns),
                $index->unique ? 'true' : 'false',
            );
        }

        foreach ($operation->table->foreignKeys as $fk) {
            $lines[] = \sprintf(
                '    $table->foreignKey(%s, %s, %s, %s, %s, %s);',
                $this->exportValue($fk->name),
                $this->exportValue($fk->columns),
                $this->exportValue($fk->referenceTable),
                $this->exportValue($fk->referenceColumns),
                $fk->onDelete !== null ? $this->exportValue($fk->onDelete) : 'null',
                $fk->onUpdate !== null ? $this->exportValue($fk->onUpdate) : 'null',
            );
        }

        $lines[] = '});';

        return $lines;
    }

    private function columnBuilderLine(Column $column): string
    {
        $typeExpression = $column->databaseType !== null
            ? $this->exportValue($column->databaseType)
            : 'ColumnType::' . ucfirst($column->requireType()->value);
        $base = \sprintf("\$table->column('%s', %s)", $column->name, $typeExpression);

        if ($column->databaseType === null && $column->length !== null) {
            $base .= \sprintf('->length(%d)', $column->length);
        } elseif ($column->databaseType === null && $column->requireType() === ColumnType::String) {
            $base .= '->length(255)';
        }
        if ($column->databaseType === null && $column->precision !== null && $column->scale !== null) {
            $base .= \sprintf('->precision(%d, %d)', $column->precision, $column->scale);
        } elseif ($column->databaseType === null && $column->requireType() === ColumnType::Decimal) {
            $base .= '->precision(10, 2)';
        }

        if ($column->nullable) {
            $base .= '->nullable()';
        }
        if ($column->default !== null) {
            $base .= '->default(' . $this->exportValue($column->default) . ')';
        }
        if ($column->primaryKey) {
            $base .= '->primaryKey()';
        }
        if ($column->autoIncrement) {
            $base .= '->autoIncrement()';
        }

        return $base . ';';
    }

    private function columnExpression(Column $column): string
    {
        $parts = [
            'name: ' . $this->exportValue($column->name),
        ];
        if ($column->databaseType === null) {
            $parts[] = 'type: ColumnType::' . ucfirst($column->requireType()->value);
        }

        if ($column->nullable) {
            $parts[] = 'nullable: true';
        }
        if ($column->default !== null) {
            $parts[] = 'default: ' . $this->exportValue($column->default);
        }
        if ($column->primaryKey) {
            $parts[] = 'primaryKey: true';
        }
        if ($column->autoIncrement) {
            $parts[] = 'autoIncrement: true';
        }
        if ($column->length !== null) {
            $parts[] = 'length: ' . $this->exportValue($column->length);
        }
        if ($column->precision !== null) {
            $parts[] = 'precision: ' . $this->exportValue($column->precision);
        }
        if ($column->scale !== null) {
            $parts[] = 'scale: ' . $this->exportValue($column->scale);
        }
        if ($column->unsigned) {
            $parts[] = 'unsigned: true';
        }
        if ($column->comment !== null) {
            $parts[] = 'comment: ' . $this->exportValue($column->comment);
        }
        if ($column->enumValues !== []) {
            $parts[] = 'enumValues: ' . $this->exportValue($column->enumValues);
        }
        if ($column->databaseType !== null) {
            $parts[] = 'databaseType: ' . $this->exportValue($column->databaseType);
        }

        return 'new Column(' . implode(', ', $parts) . ')';
    }

    /**
     * @return list<string>
     */
    private function alterColumnLines(AlterColumn $operation, bool $reverse): array
    {
        if ($reverse && $operation->oldColumn === null) {
            return [
                \sprintf(
                    '// TODO: restore previous definition for altered column %s.%s manually for rollback.',
                    $operation->tableName,
                    $operation->columnName,
                ),
            ];
        }

        $targetColumn = $reverse ? $operation->oldColumn : $operation->newColumn;
        if ($targetColumn === null) {
            throw new \LogicException('Old column is required to reverse an alter column operation.');
        }

        return [
            \sprintf(
                "\$schema->alterColumn('%s', '%s', %s);",
                $operation->tableName,
                $operation->columnName,
                $this->columnExpression($targetColumn),
            ),
        ];
    }

    private function exportValue(mixed $value): string
    {
        if (!\is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $items = [];
        if (array_is_list($value)) {
            foreach ($value as $item) {
                $items[] = $this->exportValue($item);
            }

            return '[' . implode(', ', $items) . ']';
        }

        foreach ($value as $key => $item) {
            $items[] = $this->exportValue($key) . ' => ' . $this->exportValue($item);
        }

        return '[' . implode(', ', $items) . ']';
    }
}
