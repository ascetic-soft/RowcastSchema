<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final class PostgresTypeMapper implements TypeMapperInterface
{
    public function toSqlType(Column $column): string
    {
        if ($column->databaseType !== null) {
            return $column->databaseType;
        }

        return match ($column->type) {
            ColumnType::Integer => $column->autoIncrement ? 'SERIAL' : 'INTEGER',
            ColumnType::Smallint => $column->autoIncrement ? 'SMALLSERIAL' : 'SMALLINT',
            ColumnType::Bigint => $column->autoIncrement ? 'BIGSERIAL' : 'BIGINT',
            ColumnType::String => \sprintf('VARCHAR(%d)', $column->length ?? 255),
            ColumnType::Text => 'TEXT',
            ColumnType::Boolean => 'BOOLEAN',
            ColumnType::Decimal => \sprintf('NUMERIC(%d,%d)', $column->precision, $column->scale),
            ColumnType::Float => 'REAL',
            ColumnType::Double => 'DOUBLE PRECISION',
            ColumnType::Datetime => 'TIMESTAMP(0) WITHOUT TIME ZONE',
            ColumnType::Date => 'DATE',
            ColumnType::Time => 'TIME(0) WITHOUT TIME ZONE',
            ColumnType::Timestamp => 'TIMESTAMP(0) WITHOUT TIME ZONE',
            ColumnType::Timestamptz => 'TIMESTAMP(0) WITH TIME ZONE',
            ColumnType::Uuid => 'UUID',
            ColumnType::Json => 'JSONB',
            ColumnType::Binary => 'BYTEA',
            ColumnType::Enum => 'TEXT',
        };
    }

    public function toAbstractType(string $dbType): ColumnType
    {
        $normalized = strtolower($dbType);

        return match (true) {
            str_contains($normalized, 'smallserial') => ColumnType::Smallint,
            str_contains($normalized, 'serial') => ColumnType::Integer,
            $normalized === 'int2',
            str_contains($normalized, 'smallint') => ColumnType::Smallint,
            $normalized === 'int8',
            str_contains($normalized, 'bigint') || str_contains($normalized, 'bigserial') => ColumnType::Bigint,
            $normalized === 'int4',
            str_contains($normalized, 'integer') => ColumnType::Integer,
            $normalized === 'bpchar',
            str_contains($normalized, 'character varying') || str_contains($normalized, 'varchar') => ColumnType::String,
            str_contains($normalized, 'text') => ColumnType::Text,
            $normalized === 'bool',
            str_contains($normalized, 'boolean') => ColumnType::Boolean,
            str_contains($normalized, 'numeric') || str_contains($normalized, 'decimal') => ColumnType::Decimal,
            $normalized === 'float8',
            str_contains($normalized, 'double precision') => ColumnType::Double,
            $normalized === 'float4',
            str_contains($normalized, 'real') => ColumnType::Float,
            str_contains($normalized, 'timestamptz'),
            str_contains($normalized, 'with time zone') => ColumnType::Timestamptz,
            str_contains($normalized, 'timestamp') => ColumnType::Datetime,
            $normalized === 'date' => ColumnType::Date,
            str_contains($normalized, 'time') => ColumnType::Time,
            $normalized === 'uuid' => ColumnType::Uuid,
            str_contains($normalized, 'json') => ColumnType::Json,
            str_contains($normalized, 'bytea') => ColumnType::Binary,
            default => ColumnType::String,
        };
    }
}
