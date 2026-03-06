<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Schema;

final readonly class ForeignKey
{
    /**
     * @param list<string> $columns
     * @param list<string> $referenceColumns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $referenceTable,
        public array $referenceColumns,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Foreign key name cannot be empty.');
        }

        if ($columns === [] || $referenceColumns === []) {
            throw new \InvalidArgumentException('Foreign key columns cannot be empty.');
        }

        if (\count($columns) !== \count($referenceColumns)) {
            throw new \InvalidArgumentException('Foreign key columns and reference columns size mismatch.');
        }
    }
}
