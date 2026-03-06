<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\TypeMapper\MysqlTypeMapper;
use PHPUnit\Framework\TestCase;

final class MysqlTypeMapperTest extends TestCase
{
    public function testMapsTimestamptzToSqlType(): void
    {
        $mapper = new MysqlTypeMapper();
        $column = new Column('created_at', ColumnType::Timestamptz);

        self::assertSame('TIMESTAMP', $mapper->toSqlType($column));
    }
}
