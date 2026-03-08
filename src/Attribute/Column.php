<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Attribute;

use AsceticSoft\RowcastSchema\Schema\ColumnType;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public ?string $name = null,
        public ?ColumnType $type = null,
        public ?bool $nullable = null,
        public mixed $default = null,
        public bool $primaryKey = false,
        public bool $autoIncrement = false,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public bool $unsigned = false,
        public ?string $comment = null,
        public ?string $databaseType = null,
    ) {
    }
}
