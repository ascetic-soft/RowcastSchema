<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\TypeMapper;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\TypeMapper\PostgresTypeMapper;
use PHPUnit\Framework\TestCase;

final class PostgresTypeMapperTest extends TestCase
{
    public function testUsesSerialForAutoIncrementIntegers(): void
    {
        $mapper = new PostgresTypeMapper();

        self::assertSame('SERIAL', $mapper->toSqlType(new Column('id', ColumnType::Integer, autoIncrement: true)));
        self::assertSame('SMALLSERIAL', $mapper->toSqlType(new Column('id', ColumnType::Smallint, autoIncrement: true)));
        self::assertSame('BIGSERIAL', $mapper->toSqlType(new Column('id', ColumnType::Bigint, autoIncrement: true)));
    }

    public function testReturnsCustomDatabaseTypeAsIs(): void
    {
        $mapper = new PostgresTypeMapper();
        $column = new Column('payload', ColumnType::Json, databaseType: 'JSON');

        self::assertSame('JSON', $mapper->toSqlType($column));
    }

    public function testMapsTimestamptzToSqlType(): void
    {
        $mapper = new PostgresTypeMapper();
        $column = new Column('created_at', ColumnType::Timestamptz);

        self::assertSame('TIMESTAMP(0) WITH TIME ZONE', $mapper->toSqlType($column));
    }

    public function testMapsVariousAbstractTypesToSqlTypes(): void
    {
        $mapper = new PostgresTypeMapper();

        self::assertSame('INTEGER', $mapper->toSqlType(new Column('age', ColumnType::Integer)));
        self::assertSame('VARCHAR(255)', $mapper->toSqlType(new Column('name', ColumnType::String)));
        self::assertSame('NUMERIC(10,2)', $mapper->toSqlType(new Column('amount', ColumnType::Decimal, precision: 10, scale: 2)));
        self::assertSame('JSONB', $mapper->toSqlType(new Column('meta', ColumnType::Json)));
        self::assertSame('BYTEA', $mapper->toSqlType(new Column('blob_data', ColumnType::Binary)));
        self::assertSame('TEXT', $mapper->toSqlType(new Column('status', ColumnType::Enum, enumValues: ['draft'])));
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

    public function testMapsCommonAliasesToAbstractTypes(): void
    {
        $mapper = new PostgresTypeMapper();

        self::assertSame(ColumnType::Smallint, $mapper->toAbstractType('int2'));
        self::assertSame(ColumnType::Bigint, $mapper->toAbstractType('int8'));
        self::assertSame(ColumnType::Integer, $mapper->toAbstractType('int4'));
        self::assertSame(ColumnType::String, $mapper->toAbstractType('bpchar'));
        self::assertSame(ColumnType::Boolean, $mapper->toAbstractType('bool'));
        self::assertSame(ColumnType::Double, $mapper->toAbstractType('float8'));
        self::assertSame(ColumnType::Float, $mapper->toAbstractType('float4'));
        self::assertSame(ColumnType::Decimal, $mapper->toAbstractType('NUMERIC(10,2)'));
        self::assertSame(ColumnType::Json, $mapper->toAbstractType('jsonb'));
        self::assertSame(ColumnType::Binary, $mapper->toAbstractType('bytea'));
        self::assertSame(ColumnType::Date, $mapper->toAbstractType('date'));
        self::assertSame(ColumnType::Time, $mapper->toAbstractType('TIME(0) WITHOUT TIME ZONE'));
        self::assertNull($mapper->toAbstractType('some_unknown_type'));
    }
}
