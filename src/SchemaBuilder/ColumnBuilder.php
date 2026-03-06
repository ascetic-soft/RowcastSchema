<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\SchemaBuilder;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final class ColumnBuilder
{
    private bool $nullable = false;
    private mixed $default = null;
    private bool $primaryKey = false;
    private bool $autoIncrement = false;
    private ?int $length = null;
    private ?int $precision = null;
    private ?int $scale = null;
    private bool $unsigned = false;
    private ?string $comment = null;
    /** @var list<string> */
    private array $enumValues = [];
    private ?string $databaseType = null;

    public function __construct(
        private readonly string $name,
        private readonly ColumnType $type,
    ) {
    }

    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function primaryKey(bool $primaryKey = true): self
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    public function autoIncrement(bool $autoIncrement = true): self
    {
        $this->autoIncrement = $autoIncrement;
        return $this;
    }

    public function length(int $length): self
    {
        $this->length = $length;
        return $this;
    }

    public function precision(int $precision, int $scale): self
    {
        $this->precision = $precision;
        $this->scale = $scale;
        return $this;
    }

    public function unsigned(bool $unsigned = true): self
    {
        $this->unsigned = $unsigned;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @param list<string> $values
     */
    public function values(array $values): self
    {
        $this->enumValues = $values;
        return $this;
    }

    public function databaseType(string $databaseType): self
    {
        $this->databaseType = $databaseType;
        return $this;
    }

    public function toColumn(): Column
    {
        $length = $this->length;
        if ($this->databaseType === null && $this->type === ColumnType::String && $length === null) {
            $length = 255;
        }

        return new Column(
            name: $this->name,
            type: $this->type,
            nullable: $this->nullable,
            default: $this->default,
            primaryKey: $this->primaryKey,
            autoIncrement: $this->autoIncrement,
            length: $length,
            precision: $this->precision,
            scale: $this->scale,
            unsigned: $this->unsigned,
            comment: $this->comment,
            enumValues: $this->enumValues,
            databaseType: $this->databaseType,
        );
    }
}
