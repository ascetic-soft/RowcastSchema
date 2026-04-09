<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

abstract class AbstractTypeMapper implements TypeMapperInterface
{
    final public function toSqlType(Column $column): string
    {
        return $column->databaseType ?? $this->mapToSqlType($column);
    }

    final public function toAbstractType(string $dbType): ?ColumnType
    {
        return $this->mapToAbstractType(strtolower($dbType));
    }

    abstract protected function mapToSqlType(Column $column): string;

    abstract protected function mapToAbstractType(string $normalizedType): ?ColumnType;
}
