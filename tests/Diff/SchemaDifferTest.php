<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Diff;

use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use PHPUnit\Framework\TestCase;

final class SchemaDifferTest extends TestCase
{
    public function testDetectsCreateTableAndAddColumn(): void
    {
        $from = new Schema([
            'users' => new Table(
                name: 'users',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                ],
                primaryKey: ['id'],
            ),
        ]);

        $to = new Schema([
            'users' => new Table(
                name: 'users',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                    'email' => new Column('email', ColumnType::String, length: 255),
                ],
                primaryKey: ['id'],
            ),
            'orders' => new Table(
                name: 'orders',
                columns: [
                    'id' => new Column('id', ColumnType::Uuid, primaryKey: true),
                ],
                primaryKey: ['id'],
            ),
        ]);

        $operations = (new SchemaDiffer())->diff($from, $to);
        self::assertCount(2, $operations);
        self::assertInstanceOf(AddColumn::class, $operations[0]);
        self::assertInstanceOf(CreateTable::class, $operations[1]);
    }
}
