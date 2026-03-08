<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Schema;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\Table;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    public function testHasColumnAndGetColumn(): void
    {
        $id = new Column('id', ColumnType::Integer, primaryKey: true);
        $table = new Table(
            name: 'users',
            columns: [
                'id' => $id,
            ],
            primaryKey: ['id'],
        );

        self::assertTrue($table->hasColumn('id'));
        self::assertFalse($table->hasColumn('email'));
        self::assertSame($id, $table->getColumn('id'));
        self::assertNull($table->getColumn('email'));
    }

    public function testThrowsWhenNameIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Table name cannot be empty.');

        new Table('', ['id' => new Column('id', ColumnType::Integer)]);
    }

    public function testThrowsWhenColumnsAreEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Table must contain at least one column.');

        new Table('users', []);
    }
}
