<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Schema;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use PHPUnit\Framework\TestCase;

final class ColumnTest extends TestCase
{
    public function testCreatesDecimalColumnWithPrecisionAndScale(): void
    {
        $column = new Column('amount', ColumnType::Decimal, precision: 10, scale: 2);

        self::assertSame('amount', $column->name);
        self::assertSame(10, $column->precision);
        self::assertSame(2, $column->scale);
    }

    public function testThrowsWhenNameIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Column name cannot be empty.');

        new Column('', ColumnType::String);
    }

    public function testThrowsWhenCustomDatabaseTypeIsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom database type cannot be empty.');

        new Column('payload', ColumnType::Json, databaseType: '  ');
    }

    public function testThrowsWhenTypeAndDatabaseTypeAreBothMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Column requires either "type" or "databaseType".');

        new Column('payload');
    }

    public function testThrowsWhenDecimalMissingPrecisionOrScale(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Decimal column requires "precision" and "scale".');

        new Column('amount', ColumnType::Decimal, precision: 10);
    }

    public function testThrowsWhenEnumValuesAreEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Enum column requires non-empty "enumValues".');

        new Column('status', ColumnType::Enum);
    }
}
