<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\TypeMapper\PostgresTypeMapper;
use PHPUnit\Framework\TestCase;

final class PostgresTypeMapperTest extends TestCase
{
    public function testMapsTimestamptzToSqlType(): void
    {
        $mapper = new PostgresTypeMapper();
        $column = new Column('created_at', ColumnType::Timestamptz);

        self::assertSame('TIMESTAMP(0) WITH TIME ZONE', $mapper->toSqlType($column));
    }

    public function testMapsTimestamptzSqlTypeToAbstractType(): void
    {
        $mapper = new PostgresTypeMapper();

        self::assertSame(ColumnType::Timestamptz, $mapper->toAbstractType('timestamptz'));
        self::assertSame(ColumnType::Timestamptz, $mapper->toAbstractType('TIMESTAMP(0) WITH TIME ZONE'));
    }

    public function testMapsTimestampWithoutTimeZoneToDatetime(): void
    {
        $mapper = new PostgresTypeMapper();

        self::assertSame(ColumnType::Datetime, $mapper->toAbstractType('TIMESTAMP(0) WITHOUT TIME ZONE'));
    }
}
