<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\SchemaBuilder;

use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\Table;

final class TableBuilder
{
    /** @var array<string, ColumnBuilder> */
    private array $columns = [];
    /** @var list<string> */
    private array $primaryKey = [];
    /** @var array<string, Index> */
    private array $indexes = [];
    /** @var array<string, ForeignKey> */
    private array $foreignKeys = [];

    public function __construct(private readonly string $tableName)
    {
    }

    public function integer(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Integer);
    }

    public function string(string $name, int $length): ColumnBuilder
    {
        return $this->column($name, ColumnType::String)->length($length);
    }

    public function text(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Text);
    }

    public function uuid(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Uuid);
    }

    public function datetime(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Datetime);
    }

    public function decimal(string $name, int $precision, int $scale): ColumnBuilder
    {
        return $this->column($name, ColumnType::Decimal)->precision($precision, $scale);
    }

    public function boolean(string $name): ColumnBuilder
    {
        return $this->column($name, ColumnType::Boolean);
    }

    /**
     * @param list<string> $columns
     */
    public function primaryKey(array $columns): self
    {
        $this->primaryKey = $columns;
        return $this;
    }

    /**
     * @param list<string> $columns
     */
    public function index(string $name, array $columns, bool $unique = false): self
    {
        $this->indexes[$name] = new Index($name, $columns, $unique);
        return $this;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $referenceColumns
     */
    public function foreignKey(
        string $name,
        array $columns,
        string $referenceTable,
        array $referenceColumns,
        ?string $onDelete = null,
        ?string $onUpdate = null,
    ): self {
        $this->foreignKeys[$name] = new ForeignKey(
            $name,
            $columns,
            $referenceTable,
            $referenceColumns,
            $onDelete,
            $onUpdate,
        );
        return $this;
    }

    public function toTable(): Table
    {
        $columns = [];
        $autoPrimary = [];
        foreach ($this->columns as $name => $builder) {
            $column = $builder->toColumn();
            $columns[$name] = $column;
            if ($column->primaryKey) {
                $autoPrimary[] = $name;
            }
        }

        $primaryKey = $this->primaryKey !== [] ? $this->primaryKey : $autoPrimary;

        return new Table(
            name: $this->tableName,
            columns: $columns,
            primaryKey: $primaryKey,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
        );
    }

    private function column(string $name, ColumnType $type): ColumnBuilder
    {
        $builder = new ColumnBuilder($name, $type);
        $this->columns[$name] = $builder;
        return $builder;
    }
}
