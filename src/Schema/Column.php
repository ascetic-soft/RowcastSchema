<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Schema;

final readonly class Column
{
    /**
     * @param list<string> $enumValues
     */
    public function __construct(
        public string $name,
        public ColumnType $type,
        public bool $nullable = false,
        public mixed $default = null,
        public bool $primaryKey = false,
        public bool $autoIncrement = false,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public bool $unsigned = false,
        public ?string $comment = null,
        public array $enumValues = [],
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Column name cannot be empty.');
        }

        if ($this->type === ColumnType::String && $this->length === null) {
            throw new \InvalidArgumentException('String column requires "length".');
        }

        if ($this->type === ColumnType::Decimal && ($this->precision === null || $this->scale === null)) {
            throw new \InvalidArgumentException('Decimal column requires "precision" and "scale".');
        }

        if ($this->type === ColumnType::Enum && $this->enumValues === []) {
            throw new \InvalidArgumentException('Enum column requires non-empty "enumValues".');
        }
    }
}
