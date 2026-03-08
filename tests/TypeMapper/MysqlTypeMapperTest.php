<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\TypeMapper\MysqlTypeMapper;
use PHPUnit\Framework\TestCase;

final class MysqlTypeMapperTest extends TestCase
{
    public function testReturnsCustomDatabaseTypeAsIs(): void
    {
        $mapper = new MysqlTypeMapper();
        $column = new Column('status', ColumnType::String, databaseType: 'TINYTEXT');

        self::assertSame('TINYTEXT', $mapper->toSqlType($column));
    }

    public function testMapsEnumToSqlTypeWithEscapedValues(): void
    {
        $mapper = new MysqlTypeMapper();
        $column = new Column('status', ColumnType::Enum, enumValues: ['draft', "o'hare"]);

        self::assertSame("ENUM('draft', 'o\\'hare')", $mapper->toSqlType($column));
    }

    public function testMapsTimestamptzToSqlType(): void
    {
        $mapper = new MysqlTypeMapper();
        $column = new Column('created_at', ColumnType::Timestamptz);

        self::assertSame('TIMESTAMP', $mapper->toSqlType($column));
    }

    public function testMapsKnownDbTypesToAbstractTypes(): void
    {
        $mapper = new MysqlTypeMapper();

        self::assertSame(ColumnType::Boolean, $mapper->toAbstractType('tinyint(1)'));
        self::assertSame(ColumnType::Integer, $mapper->toAbstractType('int(11)'));
        self::assertSame(ColumnType::Uuid, $mapper->toAbstractType('char(36)'));
        self::assertSame(ColumnType::Enum, $mapper->toAbstractType("enum('a','b')"));
        self::assertNull($mapper->toAbstractType('unknown_type'));
    }
}
