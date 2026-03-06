<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

use AsceticSoft\RowcastSchema\Schema\Schema;

final readonly class TableIgnoreMatcher
{
    /**
     * @param list<string|\Closure(string):bool> $rules
     */
    public function __construct(
        private array $rules = [],
        private string $migrationTableName = '_rowcast_migrations',
    ) {
    }

    public function shouldIgnore(string $tableName): bool
    {
        if ($tableName === $this->migrationTableName) {
            return true;
        }

        foreach ($this->rules as $rule) {
            if (\is_string($rule) && preg_match($rule, $tableName) === 1) {
                return true;
            }

            if ($rule instanceof \Closure && $rule($tableName)) {
                return true;
            }
        }

        return false;
    }

    public function filterSchema(Schema $schema): Schema
    {
        $filtered = [];
        foreach ($schema->tables as $tableName => $table) {
            if ($this->shouldIgnore($tableName)) {
                continue;
            }
            $filtered[$tableName] = $table;
        }

        return new Schema($filtered);
    }
}
