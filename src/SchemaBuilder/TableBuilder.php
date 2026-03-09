<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\SchemaBuilder;

use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\ReferentialAction;
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

    public function column(string $name, ColumnType|string $type): ColumnBuilder
    {
        [$resolvedType, $customDatabaseType] = $this->resolveType($type);
        $builder = new ColumnBuilder($name, $resolvedType);
        if ($customDatabaseType !== null) {
            $builder->databaseType($customDatabaseType);
        }
        $this->columns[$name] = $builder;
        return $builder;
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
        ReferentialAction|string|null $onDelete = null,
        ReferentialAction|string|null $onUpdate = null,
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

    /**
     * @return array{0: ColumnType, 1: ?string}
     */
    private function resolveType(ColumnType|string $type): array
    {
        if ($type instanceof ColumnType) {
            return [$type, null];
        }

        $normalized = \trim($type);
        $resolved = ColumnType::tryFrom($this->normalizeKnownTypeAlias(\strtolower($normalized)));
        if ($resolved instanceof ColumnType) {
            return [$resolved, null];
        }

        return [ColumnType::Text, $normalized];
    }

    private function normalizeKnownTypeAlias(string $normalizedType): string
    {
        return match ($normalizedType) {
            'jsonb' => ColumnType::Json->value,
            default => $normalizedType,
        };
    }

}
