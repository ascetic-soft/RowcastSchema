<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Table
{
    public function __construct(
        public ?string $name = null,
        public ?string $engine = null,
        public ?string $charset = null,
        public ?string $collation = null,
    ) {
    }
}
