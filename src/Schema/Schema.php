<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Schema;

final readonly class Schema
{
    /**
     * @param array<string, Table> $tables
     */
    public function __construct(public array $tables = [])
    {
    }

    public function hasTable(string $table): bool
    {
        return isset($this->tables[$table]);
    }

    public function getTable(string $table): ?Table
    {
        return $this->tables[$table] ?? null;
    }
}
