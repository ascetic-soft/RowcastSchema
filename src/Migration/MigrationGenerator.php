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
            $operation instanceof AlterColumn => $reverse
                ? [
                    \sprintf(
                        "\$schema->alterColumn('%s', %s, %s);",
                        $operation->tableName,
                        $this->columnExpression($operation->newColumn),
                        $this->columnExpression($operation->oldColumn),
                    ),
                ]
                : [
                    \sprintf(
                        "\$schema->alterColumn('%s', %s, %s);",
                        $operation->tableName,
                        $this->columnExpression($operation->oldColumn),
                        $this->columnExpression($operation->newColumn),
                    ),
                ],
            $operation instanceof AddIndex => [
                $reverse
                    ? \sprintf("\$schema->dropIndex('%s', '%s');", $operation->tableName, $operation->index->name)
                    : \sprintf(
                        "\$schema->addIndex('%s', '%s', %s, %s);",
                        $operation->tableName,
                        $operation->index->name,
                        var_export($operation->index->columns, true),
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
                        var_export($operation->foreignKey->columns, true),
                        $operation->foreignKey->referenceTable,
                        var_export($operation->foreignKey->referenceColumns, true),
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
            $lines[] = \sprintf('    $table->primaryKey(%s);', var_export($operation->table->primaryKey, true));
        }

        foreach ($operation->table->indexes as $index) {
            $lines[] = \sprintf(
                '    $table->index(%s, %s, %s);',
                var_export($index->name, true),
                var_export($index->columns, true),
                $index->unique ? 'true' : 'false',
            );
        }

        foreach ($operation->table->foreignKeys as $fk) {
            $lines[] = \sprintf(
                '    $table->foreignKey(%s, %s, %s, %s, %s, %s);',
                var_export($fk->name, true),
                var_export($fk->columns, true),
                var_export($fk->referenceTable, true),
                var_export($fk->referenceColumns, true),
                $fk->onDelete !== null ? var_export($fk->onDelete, true) : 'null',
                $fk->onUpdate !== null ? var_export($fk->onUpdate, true) : 'null',
            );
        }

        $lines[] = '});';

        return $lines;
    }

    private function columnBuilderLine(Column $column): string
    {
        $base = match ($column->type->value) {
            'integer' => \sprintf("\$table->integer('%s')", $column->name),
            'string' => \sprintf("\$table->string('%s', %d)", $column->name, $column->length ?? 255),
            'text' => \sprintf("\$table->text('%s')", $column->name),
            'uuid' => \sprintf("\$table->uuid('%s')", $column->name),
            'datetime' => \sprintf("\$table->datetime('%s')", $column->name),
            'decimal' => \sprintf("\$table->decimal('%s', %d, %d)", $column->name, $column->precision ?? 10, $column->scale ?? 2),
            'boolean' => \sprintf("\$table->boolean('%s')", $column->name),
            default => \sprintf('// TODO: unsupported column type %s for %s', $column->type->value, $column->name),
        };

        if (str_starts_with($base, '//')) {
            return $base;
        }

        if ($column->nullable) {
            $base .= '->nullable()';
        }
        if ($column->default !== null) {
            $base .= '->default(' . var_export($column->default, true) . ')';
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
            'name: ' . var_export($column->name, true),
            'type: ColumnType::' . ucfirst($column->type->value),
            'nullable: ' . ($column->nullable ? 'true' : 'false'),
            'default: ' . var_export($column->default, true),
            'primaryKey: ' . ($column->primaryKey ? 'true' : 'false'),
            'autoIncrement: ' . ($column->autoIncrement ? 'true' : 'false'),
            'length: ' . var_export($column->length, true),
            'precision: ' . var_export($column->precision, true),
            'scale: ' . var_export($column->scale, true),
            'unsigned: ' . ($column->unsigned ? 'true' : 'false'),
            'comment: ' . var_export($column->comment, true),
            'enumValues: ' . var_export($column->enumValues, true),
        ];

        return 'new Column(' . implode(', ', $parts) . ')';
    }
}
