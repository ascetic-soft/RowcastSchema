<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

interface MigrationRepositoryInterface
{
    public function ensureTable(): void;

    /**
     * @return list<string>
     */
    public function getApplied(): array;

    public function markApplied(string $version): void;

    public function markRolledBack(string $version): void;
}
