<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Index
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public string $name,
        public array $columns = [],
        public bool $unique = false,
    ) {
    }
}
