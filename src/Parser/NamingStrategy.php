<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

final class NamingStrategy
{
    public function propertyToColumnName(string $propertyName): string
    {
        return $this->camelToSnake($propertyName);
    }

    public function classToTableName(string $shortClassName): string
    {
        return $this->camelToSnake($shortClassName) . 's';
    }

    private function camelToSnake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
