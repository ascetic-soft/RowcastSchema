<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Attribute;

use AsceticSoft\RowcastSchema\Attribute\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use PHPUnit\Framework\TestCase;

final class ColumnTest extends TestCase
{
    public function testStoresDefaults(): void
    {
        $attribute = new Column();

        self::assertNull($attribute->name);
        self::assertNull($attribute->type);
        self::assertNull($attribute->nullable);
        self::assertNull($attribute->default);
        self::assertFalse($attribute->primaryKey);
        self::assertFalse($attribute->autoIncrement);
        self::assertNull($attribute->length);
        self::assertNull($attribute->precision);
        self::assertNull($attribute->scale);
        self::assertFalse($attribute->unsigned);
        self::assertNull($attribute->comment);
        self::assertNull($attribute->databaseType);
    }

    public function testStoresExplicitEnumType(): void
    {
        $attribute = new Column(type: ColumnType::Integer);

        self::assertSame(ColumnType::Integer, $attribute->type);
    }
}
