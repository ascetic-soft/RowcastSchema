<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Schema;

enum ReferentialAction: string
{
    case Cascade = 'CASCADE';
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';
    case Restrict = 'RESTRICT';
    case NoAction = 'NO ACTION';

    /**
     * Resolve enum or raw string to an uppercase SQL clause value.
     */
    public static function toSql(self|string $action): string
    {
        return $action instanceof self ? $action->value : strtoupper($action);
    }

    /**
     * Attempt to match a string to a known case; return the raw string on miss.
     */
    public static function tryFromString(string $value): self|string
    {
        return self::tryFrom(strtoupper(trim($value))) ?? $value;
    }
}
