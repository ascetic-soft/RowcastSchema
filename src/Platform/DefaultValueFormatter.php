<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Platform;

final class DefaultValueFormatter
{
    public static function format(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Default column value must be scalar.');
        }
        if (strtoupper($value) === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }

        return "'" . str_replace("'", "\\'", $value) . "'";
    }

    public static function formatSqlite(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Default column value must be scalar.');
        }
        if (strtoupper($value) === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }
}
