<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final class MysqlTypeMapper implements TypeMapperInterface
{
    public function toSqlType(Column $column): string
    {
        return $column->databaseType ?? match ($this->requireColumnType($column)) {
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

    private function requireColumnType(Column $column): ColumnType
    {
        if ($column->type instanceof ColumnType) {
            return $column->type;
        }

        throw new \LogicException(\sprintf('Column "%s" type is required when databaseType is not set.', $column->name));
    }

    public function toAbstractType(string $dbType): ?ColumnType
    {
        $normalized = strtolower($dbType);

        return match (true) {
            str_starts_with($normalized, 'tinyint(1)') => ColumnType::Boolean,
            str_starts_with($normalized, 'int') => ColumnType::Integer,
            str_starts_with($normalized, 'smallint') => ColumnType::Smallint,
            str_starts_with($normalized, 'bigint') => ColumnType::Bigint,
            str_starts_with($normalized, 'varchar') => ColumnType::String,
            str_starts_with($normalized, 'text') => ColumnType::Text,
            str_starts_with($normalized, 'decimal') => ColumnType::Decimal,
            str_starts_with($normalized, 'float') => ColumnType::Float,
            str_starts_with($normalized, 'double') => ColumnType::Double,
            str_starts_with($normalized, 'datetime') => ColumnType::Datetime,
            str_starts_with($normalized, 'date') => ColumnType::Date,
            str_starts_with($normalized, 'time') => ColumnType::Time,
            str_starts_with($normalized, 'timestamp') => ColumnType::Timestamp,
            str_starts_with($normalized, 'char(36)') => ColumnType::Uuid,
            str_starts_with($normalized, 'json') => ColumnType::Json,
            str_starts_with($normalized, 'blob') => ColumnType::Binary,
            str_starts_with($normalized, 'enum') => ColumnType::Enum,
            default => null,
        };
    }
}
