<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final class SqliteTypeMapper extends AbstractTypeMapper
{
    protected function mapToSqlType(Column $column): string
    {
        return match ($column->requireType()) {
            ColumnType::Integer,
            ColumnType::Smallint,
            ColumnType::Bigint,
            ColumnType::Boolean => 'INTEGER',
            ColumnType::String,
            ColumnType::Text,
            ColumnType::Uuid,
            ColumnType::Enum,
            ColumnType::Datetime,
            ColumnType::Timestamptz,
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

    protected function mapToAbstractType(string $normalizedType): ?ColumnType
    {
        return match (true) {
            str_contains($normalizedType, 'int') => ColumnType::Integer,
            str_contains($normalizedType, 'char'), str_contains($normalizedType, 'text'), str_contains($normalizedType, 'clob') => ColumnType::Text,
            str_contains($normalizedType, 'real'), str_contains($normalizedType, 'floa'), str_contains($normalizedType, 'doub') => ColumnType::Double,
            str_contains($normalizedType, 'blob') => ColumnType::Binary,
            default => null,
        };
    }
}
