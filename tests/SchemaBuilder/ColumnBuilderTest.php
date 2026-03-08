<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\SchemaBuilder;

use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\SchemaBuilder\ColumnBuilder;
use PHPUnit\Framework\TestCase;

final class ColumnBuilderTest extends TestCase
{
    public function testAppliesDefaultStringLength(): void
    {
        $column = new ColumnBuilder('email', ColumnType::String)->toColumn();

        self::assertSame(255, $column->length);
    }

    public function testDoesNotApplyDefaultLengthWhenCustomDatabaseTypeIsUsed(): void
    {
        $column = new ColumnBuilder('email', ColumnType::String)
            ->databaseType('CITEXT')
            ->toColumn();

        self::assertNull($column->length);
        self::assertSame('CITEXT', $column->databaseType);
    }

    public function testBuildsConfiguredColumn(): void
    {
        $column = new ColumnBuilder('amount', ColumnType::Decimal)
            ->nullable()
            ->default('0.00')
            ->primaryKey()
            ->autoIncrement()
            ->precision(10, 2)
            ->unsigned()
            ->comment('Money amount')
            ->toColumn();

        self::assertTrue($column->nullable);
        self::assertSame('0.00', $column->default);
        self::assertTrue($column->primaryKey);
        self::assertTrue($column->autoIncrement);
        self::assertSame(10, $column->precision);
        self::assertSame(2, $column->scale);
        self::assertTrue($column->unsigned);
        self::assertSame('Money amount', $column->comment);
    }

    public function testSupportsLengthAndEnumValuesConfiguration(): void
    {
        $column = new ColumnBuilder('status', ColumnType::Enum)
            ->length(32)
            ->values(['draft', 'published'])
            ->nullable(false)
            ->primaryKey(false)
            ->autoIncrement(false)
            ->unsigned(false)
            ->toColumn();

        self::assertSame(32, $column->length);
        self::assertSame(['draft', 'published'], $column->enumValues);
        self::assertFalse($column->nullable);
        self::assertFalse($column->primaryKey);
        self::assertFalse($column->autoIncrement);
        self::assertFalse($column->unsigned);
    }
}
