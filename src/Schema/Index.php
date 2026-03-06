<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Schema;

final readonly class Index
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique = false,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Index name cannot be empty.');
        }

        if ($columns === []) {
            throw new \InvalidArgumentException('Index must contain at least one column.');
        }
    }
}
