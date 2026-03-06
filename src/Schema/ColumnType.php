<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Schema;

enum ColumnType: string
{
    case Integer = 'integer';
    case Smallint = 'smallint';
    case Bigint = 'bigint';
    case String = 'string';
    case Text = 'text';
    case Boolean = 'boolean';
    case Decimal = 'decimal';
    case Float = 'float';
    case Double = 'double';
    case Datetime = 'datetime';
    case Date = 'date';
    case Time = 'time';
    case Timestamp = 'timestamp';
    case Uuid = 'uuid';
    case Json = 'json';
    case Binary = 'binary';
    case Enum = 'enum';
}
