<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

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
use AsceticSoft\RowcastSchema\Diff\Operation\RawSql;
use AsceticSoft\RowcastSchema\Schema\Column;

final class OperationDescriber
{
    public function describe(OperationInterface $operation): string
    {
        return match (true) {
            $operation instanceof CreateTable => \sprintf(
                '+ Create table "%s" (%d columns, %d indexes, %d FKs)',
                $operation->table->name,
                \count($operation->table->columns),
                \count($operation->table->indexes),
                \count($operation->table->foreignKeys),
            ),
            $operation instanceof DropTable => \sprintf(
                '- Drop table "%s"',
                $operation->tableName,
            ),
            $operation instanceof AddColumn => \sprintf(
                '+ Add column "%s"."%s" (%s)',
                $operation->tableName,
                $operation->column->name,
                $this->columnTypeLabel($operation->column),
            ),
            $operation instanceof DropColumn => \sprintf(
                '- Drop column "%s"."%s"',
                $operation->tableName,
                $operation->columnName,
            ),
            $operation instanceof AlterColumn => \sprintf(
                '~ Alter column "%s"."%s" (%s -> %s)',
                $operation->tableName,
                $operation->columnName,
                $operation->oldColumn !== null ? $this->columnTypeLabel($operation->oldColumn) : 'unknown',
                $this->columnTypeLabel($operation->newColumn),
            ),
            $operation instanceof AddIndex => \sprintf(
                '+ Add index "%s"."%s" (%s)%s',
                $operation->tableName,
                $operation->index->name,
                implode(', ', $operation->index->columns),
                $operation->index->unique ? ' unique' : '',
            ),
            $operation instanceof DropIndex => \sprintf(
                '- Drop index "%s"."%s"',
                $operation->tableName,
                $operation->indexName,
            ),
            $operation instanceof AddForeignKey => \sprintf(
                '+ Add FK "%s"."%s" -> %s(%s)',
                $operation->tableName,
                $operation->foreignKey->name,
                $operation->foreignKey->referenceTable,
                implode(', ', $operation->foreignKey->referenceColumns),
            ),
            $operation instanceof DropForeignKey => \sprintf(
                '- Drop FK "%s"."%s"',
                $operation->tableName,
                $operation->foreignKeyName,
            ),
            $operation instanceof RawSql => \sprintf(
                '~ Execute raw SQL (%s)',
                $this->truncateSql($operation->sql),
            ),
            default => \sprintf('~ %s', $operation::class),
        };
    }

    /**
     * @return list<string>
     */
    public function describeDetails(OperationInterface $operation): array
    {
        return match (true) {
            $operation instanceof CreateTable => $this->describeCreateTableColumns($operation),
            $operation instanceof AddColumn => [$this->formatColumnDetail($operation->column)],
            $operation instanceof AlterColumn => [$this->formatColumnDetail($operation->newColumn)],
            $operation instanceof AddForeignKey => [$this->formatForeignKeyDetail($operation)],
            $operation instanceof AddIndex => [$this->formatIndexDetail($operation)],
            default => [],
        };
    }

    /**
     * @param list<OperationInterface> $operations
     */
    public function describeSummary(array $operations): string
    {
        if ($operations === []) {
            return 'No operations.';
        }

        $groups = [
            CreateTable::class => ['count' => 0, 'label' => 'tables created'],
            DropTable::class => ['count' => 0, 'label' => 'tables dropped'],
            AddColumn::class => ['count' => 0, 'label' => 'columns added'],
            DropColumn::class => ['count' => 0, 'label' => 'columns dropped'],
            AlterColumn::class => ['count' => 0, 'label' => 'columns altered'],
            AddIndex::class => ['count' => 0, 'label' => 'indexes added'],
            DropIndex::class => ['count' => 0, 'label' => 'indexes dropped'],
            AddForeignKey::class => ['count' => 0, 'label' => 'foreign keys added'],
            DropForeignKey::class => ['count' => 0, 'label' => 'foreign keys dropped'],
            RawSql::class => ['count' => 0, 'label' => 'raw SQL statements'],
        ];

        foreach ($operations as $operation) {
            $class = $operation::class;
            if (isset($groups[$class])) {
                $groups[$class]['count']++;
            }
        }

        $parts = [];
        foreach ($groups as $group) {
            if ($group['count'] === 0) {
                continue;
            }

            $parts[] = \sprintf('%d %s', $group['count'], $group['label']);
        }

        return implode(', ', $parts);
    }

    /**
     * @return list<string>
     */
    private function describeCreateTableColumns(CreateTable $operation): array
    {
        $lines = [];
        foreach ($operation->table->columns as $column) {
            $lines[] = $this->formatColumnDetail($column);
        }

        return $lines;
    }

    private function formatColumnDetail(Column $column): string
    {
        $attributes = [];
        if ($column->nullable) {
            $attributes[] = 'nullable';
        }
        if ($column->primaryKey) {
            $attributes[] = 'PK';
        }
        if ($column->autoIncrement) {
            $attributes[] = 'AI';
        }
        if ($column->unsigned) {
            $attributes[] = 'unsigned';
        }
        if ($column->default !== null) {
            $attributes[] = 'default=' . var_export($column->default, true);
        }

        $attributeLabel = $attributes !== [] ? ' [' . implode(', ', $attributes) . ']' : '';

        return \sprintf(
            '%s %s%s',
            $column->name,
            $this->columnTypeLabel($column),
            $attributeLabel,
        );
    }

    private function formatForeignKeyDetail(AddForeignKey $operation): string
    {
        $detail = \sprintf(
            'columns: (%s), references: %s(%s)',
            implode(', ', $operation->foreignKey->columns),
            $operation->foreignKey->referenceTable,
            implode(', ', $operation->foreignKey->referenceColumns),
        );

        if ($operation->foreignKey->onDelete !== null) {
            $detail .= ', onDelete=' . $operation->foreignKey->onDelete;
        }
        if ($operation->foreignKey->onUpdate !== null) {
            $detail .= ', onUpdate=' . $operation->foreignKey->onUpdate;
        }

        return $detail;
    }

    private function formatIndexDetail(AddIndex $operation): string
    {
        return \sprintf(
            'columns: (%s)%s',
            implode(', ', $operation->index->columns),
            $operation->index->unique ? ', unique' : '',
        );
    }

    private function columnTypeLabel(Column $column): string
    {
        if ($column->databaseType !== null) {
            return $column->databaseType;
        }

        $type = $this->requireColumnType($column)->value;
        if ($column->length !== null) {
            return \sprintf('%s(%d)', $type, $column->length);
        }

        if ($column->precision !== null && $column->scale !== null) {
            return \sprintf('%s(%d,%d)', $type, $column->precision, $column->scale);
        }

        return $type;
    }

    private function requireColumnType(Column $column): \AsceticSoft\RowcastSchema\Schema\ColumnType
    {
        if ($column->type instanceof \AsceticSoft\RowcastSchema\Schema\ColumnType) {
            return $column->type;
        }

        throw new \LogicException(\sprintf('Column "%s" type is required when databaseType is not set.', $column->name));
    }

    private function truncateSql(string $sql): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
        if (\strlen($normalized) <= 80) {
            return $normalized;
        }

        return substr($normalized, 0, 77) . '...';
    }
}
