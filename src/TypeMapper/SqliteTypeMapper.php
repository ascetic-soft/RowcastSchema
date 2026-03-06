<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final class SqliteTypeMapper implements TypeMapperInterface
{
    public function toSqlType(Column $column): string
    {
        if ($column->databaseType !== null) {
            return $column->databaseType;
        }

        return match ($column->type) {
            ColumnType::Integer,
            ColumnType::Smallint,
            ColumnType::Bigint,
            ColumnType::Boolean => 'INTEGER',
            ColumnType::String,
            ColumnType::Text,
            ColumnType::Uuid,
            ColumnType::Enum,
            ColumnType::Datetime,
            ColumnType::Date,
            ColumnType::Time,
            ColumnType::Timestamp,
            ColumnType::Json => 'TEXT',
            ColumnType::Decimal,
            ColumnType::Float,
            ColumnType::Double => 'REAL',
            ColumnType::Binary => 'BLOB',
        };
    }

    public function toAbstractType(string $dbType): ColumnType
    {
        $normalized = strtolower($dbType);

        return match (true) {
            str_contains($normalized, 'int') => ColumnType::Integer,
            str_contains($normalized, 'char'), str_contains($normalized, 'text'), str_contains($normalized, 'clob') => ColumnType::Text,
            str_contains($normalized, 'real'), str_contains($normalized, 'floa'), str_contains($normalized, 'doub') => ColumnType::Double,
            str_contains($normalized, 'blob') => ColumnType::Binary,
            default => ColumnType::Text,
        };
    }
}
