<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\SchemaBuilder;

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

final class SchemaBuilder
{
    /** @var list<OperationInterface> */
    private array $operations = [];

    public function createTable(string $name, callable $callback): self
    {
        $builder = new TableBuilder($name);
        $callback($builder);
        $this->operations[] = new CreateTable($builder->toTable());
        return $this;
    }

    public function dropTable(string $name): self
    {
        $this->operations[] = new DropTable($name);
        return $this;
    }

    public function addColumn(string $table, Column $column): self
    {
        $this->operations[] = new AddColumn($table, $column);
        return $this;
    }

    public function dropColumn(string $table, string $column): self
    {
        $this->operations[] = new DropColumn($table, $column);
        return $this;
    }

    public function alterColumn(string $table, string $columnName, Column $newColumn): self
    {
        $this->operations[] = new AlterColumn($table, $columnName, $newColumn);
        return $this;
    }

    /**
     * @param list<string> $columns
     */
    public function addIndex(string $table, string $name, array $columns, bool $unique = false): self
    {
        $this->operations[] = new AddIndex($table, new Index($name, $columns, $unique));
        return $this;
    }

    public function dropIndex(string $table, string $name): self
    {
        $this->operations[] = new DropIndex($table, $name);
        return $this;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $referenceColumns
     */
    public function addForeignKey(
        string $table,
        string $name,
        array $columns,
        string $referenceTable,
        array $referenceColumns,
        ?string $onDelete = null,
        ?string $onUpdate = null,
    ): self {
        $this->operations[] = new AddForeignKey(
            $table,
            new ForeignKey($name, $columns, $referenceTable, $referenceColumns, $onDelete, $onUpdate),
        );
        return $this;
    }

    public function dropForeignKey(string $table, string $name): self
    {
        $this->operations[] = new DropForeignKey($table, $name);
        return $this;
    }

    /**
     * @return list<OperationInterface>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function reset(): void
    {
        $this->operations = [];
    }
}
