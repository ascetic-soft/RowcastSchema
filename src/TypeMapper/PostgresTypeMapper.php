<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final class PostgresTypeMapper extends AbstractTypeMapper
{
    protected function mapToSqlType(Column $column): string
    {
        return match ($column->requireType()) {
            ColumnType::Integer => $column->autoIncrement ? 'SERIAL' : 'INTEGER',
            ColumnType::Smallint => $column->autoIncrement ? 'SMALLSERIAL' : 'SMALLINT',
            ColumnType::Bigint => $column->autoIncrement ? 'BIGSERIAL' : 'BIGINT',
            ColumnType::String => \sprintf('VARCHAR(%d)', $column->length ?? 255),
            ColumnType::Text, ColumnType::Enum => 'TEXT',
            ColumnType::Boolean => 'BOOLEAN',
            ColumnType::Decimal => \sprintf('NUMERIC(%d,%d)', $column->precision, $column->scale),
            ColumnType::Float => 'REAL',
            ColumnType::Double => 'DOUBLE PRECISION',
            ColumnType::Datetime,
            ColumnType::Timestamp => 'TIMESTAMP(0) WITHOUT TIME ZONE',
            ColumnType::Date => 'DATE',
            ColumnType::Time => 'TIME(0) WITHOUT TIME ZONE',
            ColumnType::Timestamptz => 'TIMESTAMP(0) WITH TIME ZONE',
            ColumnType::Uuid => 'UUID',
            ColumnType::Json => 'JSONB',
            ColumnType::Binary => 'BYTEA',
        };
    }

    protected function mapToAbstractType(string $normalizedType): ?ColumnType
    {
        return match (true) {
            str_contains($normalizedType, 'smallserial') => ColumnType::Smallint,
            str_contains($normalizedType, 'serial') => ColumnType::Integer,
            $normalizedType === 'int2',
            str_contains($normalizedType, 'smallint') => ColumnType::Smallint,
            $normalizedType === 'int8',
            str_contains($normalizedType, 'bigint') || str_contains($normalizedType, 'bigserial') => ColumnType::Bigint,
            $normalizedType === 'int4',
            str_contains($normalizedType, 'integer') => ColumnType::Integer,
            $normalizedType === 'bpchar',
            str_contains($normalizedType, 'character varying') || str_contains($normalizedType, 'varchar') => ColumnType::String,
            str_contains($normalizedType, 'text') => ColumnType::Text,
            $normalizedType === 'bool',
            str_contains($normalizedType, 'boolean') => ColumnType::Boolean,
            str_contains($normalizedType, 'numeric') || str_contains($normalizedType, 'decimal') => ColumnType::Decimal,
            $normalizedType === 'float8',
            str_contains($normalizedType, 'double precision') => ColumnType::Double,
            $normalizedType === 'float4',
            str_contains($normalizedType, 'real') => ColumnType::Float,
            str_contains($normalizedType, 'timestamptz'),
            str_contains($normalizedType, 'with time zone') => ColumnType::Timestamptz,
            str_contains($normalizedType, 'timestamp') => ColumnType::Datetime,
            $normalizedType === 'date' => ColumnType::Date,
            str_contains($normalizedType, 'time') => ColumnType::Time,
            $normalizedType === 'uuid' => ColumnType::Uuid,
            str_contains($normalizedType, 'json') => ColumnType::Json,
            str_contains($normalizedType, 'bytea') => ColumnType::Binary,
            default => null,
        };
    }
}
