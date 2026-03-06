<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

interface TypeMapperInterface
{
    public function toSqlType(Column $column): string;

    public function toAbstractType(string $dbType): ColumnType;
}
