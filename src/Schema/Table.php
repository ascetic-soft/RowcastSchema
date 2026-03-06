<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Schema;

final readonly class Table
{
    /**
     * @param array<string, Column>     $columns
     * @param list<string>              $primaryKey
     * @param array<string, Index>      $indexes
     * @param array<string, ForeignKey> $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $primaryKey = [],
        public array $indexes = [],
        public array $foreignKeys = [],
        public ?string $engine = null,
        public ?string $charset = null,
        public ?string $collation = null,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }

        if ($columns === []) {
            throw new \InvalidArgumentException('Table must contain at least one column.');
        }
    }

    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    public function getColumn(string $name): ?Column
    {
        return $this->columns[$name] ?? null;
    }
}
