<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final class MysqlTypeMapper extends AbstractTypeMapper
{
    protected function mapToSqlType(Column $column): string
    {
        return match ($column->requireType()) {
            ColumnType::Integer => 'INT',
            ColumnType::Smallint => 'SMALLINT',
            ColumnType::Bigint => 'BIGINT',
            ColumnType::String => \sprintf('VARCHAR(%d)', $column->length ?? 255),
            ColumnType::Text => 'TEXT',
            ColumnType::Boolean => 'TINYINT(1)',
            ColumnType::Decimal => \sprintf('DECIMAL(%d,%d)', $column->precision, $column->scale),
            ColumnType::Float => 'FLOAT',
            ColumnType::Double => 'DOUBLE',
            ColumnType::Datetime => 'DATETIME',
            ColumnType::Date => 'DATE',
            ColumnType::Time => 'TIME',
            ColumnType::Timestamp,
            ColumnType::Timestamptz => 'TIMESTAMP',
            ColumnType::Uuid => 'CHAR(36)',
            ColumnType::Json => 'JSON',
            ColumnType::Binary => 'BLOB',
            ColumnType::Enum => \sprintf(
                'ENUM(%s)',
                implode(', ', array_map(
                    static fn (string $v): string => "'" . str_replace("'", "\\'", $v) . "'",
                    $column->enumValues
                )),
            ),
        };
    }

    protected function mapToAbstractType(string $normalizedType): ?ColumnType
    {
        return match (true) {
            str_starts_with($normalizedType, 'tinyint(1)') => ColumnType::Boolean,
            str_starts_with($normalizedType, 'int') => ColumnType::Integer,
            str_starts_with($normalizedType, 'smallint') => ColumnType::Smallint,
            str_starts_with($normalizedType, 'bigint') => ColumnType::Bigint,
            str_starts_with($normalizedType, 'varchar') => ColumnType::String,
            str_starts_with($normalizedType, 'text') => ColumnType::Text,
            str_starts_with($normalizedType, 'decimal') => ColumnType::Decimal,
            str_starts_with($normalizedType, 'float') => ColumnType::Float,
            str_starts_with($normalizedType, 'double') => ColumnType::Double,
            str_starts_with($normalizedType, 'datetime') => ColumnType::Datetime,
            str_starts_with($normalizedType, 'date') => ColumnType::Date,
            str_starts_with($normalizedType, 'time') => ColumnType::Time,
            str_starts_with($normalizedType, 'timestamp') => ColumnType::Timestamp,
            str_starts_with($normalizedType, 'char(36)') => ColumnType::Uuid,
            str_starts_with($normalizedType, 'json') => ColumnType::Json,
            str_starts_with($normalizedType, 'blob') => ColumnType::Binary,
            str_starts_with($normalizedType, 'enum') => ColumnType::Enum,
            default => null,
        };
    }
}
