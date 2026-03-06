<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;
use PHPUnit\Framework\TestCase;

final class SqliteTypeMapperTest extends TestCase
{
    public function testMapsAbstractToSqlType(): void
    {
        $mapper = new SqliteTypeMapper();
        $column = new Column('email', ColumnType::String, length: 255);

        self::assertSame('TEXT', $mapper->toSqlType($column));
    }

    public function testMapsTimestamptzToSqlType(): void
    {
        $mapper = new SqliteTypeMapper();
        $column = new Column('created_at', ColumnType::Timestamptz);

        self::assertSame('TEXT', $mapper->toSqlType($column));
    }

    public function testMapsSqlTypeToAbstract(): void
    {
        $mapper = new SqliteTypeMapper();
        self::assertSame(ColumnType::Integer, $mapper->toAbstractType('INTEGER'));
        self::assertSame(ColumnType::Binary, $mapper->toAbstractType('BLOB'));
    }
}
