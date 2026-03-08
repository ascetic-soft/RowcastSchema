<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;
use PHPUnit\Framework\TestCase;

final class SqliteTypeMapperTest extends TestCase
{
    public function testMapsStringToTextSqlType(): void
    {
        $mapper = new SqliteTypeMapper();
        $column = new Column('email', ColumnType::String, length: 255);

        self::assertSame('TEXT', $mapper->toSqlType($column));
    }

    public function testMapsRepresentativeAbstractTypesToSqliteTypes(): void
    {
        $mapper = new SqliteTypeMapper();

        self::assertSame('INTEGER', $mapper->toSqlType(new Column('id', ColumnType::Integer)));
        self::assertSame('INTEGER', $mapper->toSqlType(new Column('is_active', ColumnType::Boolean)));
        self::assertSame('TEXT', $mapper->toSqlType(new Column('created_at', ColumnType::Timestamptz)));
        self::assertSame('REAL', $mapper->toSqlType(new Column('price', ColumnType::Decimal, precision: 10, scale: 2)));
        self::assertSame('BLOB', $mapper->toSqlType(new Column('blob_data', ColumnType::Binary)));
    }

    public function testReturnsCustomDatabaseTypeAsIs(): void
    {
        $mapper = new SqliteTypeMapper();
        $column = new Column('payload', ColumnType::Json, databaseType: 'JSON');

        self::assertSame('JSON', $mapper->toSqlType($column));
    }

    public function testMapsSqlTypeToAbstract(): void
    {
        $mapper = new SqliteTypeMapper();

        self::assertSame(ColumnType::Integer, $mapper->toAbstractType('INTEGER'));
        self::assertSame(ColumnType::Text, $mapper->toAbstractType('VARCHAR(255)'));
        self::assertSame(ColumnType::Text, $mapper->toAbstractType('CLOB'));
        self::assertSame(ColumnType::Double, $mapper->toAbstractType('DOUBLE'));
        self::assertSame(ColumnType::Binary, $mapper->toAbstractType('BLOB'));
        self::assertSame(ColumnType::Text, $mapper->toAbstractType('JSON'));
    }
}
