<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Diff\Operation;

use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Diff\Operation\RawSql;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Table;
use PHPUnit\Framework\TestCase;

final class OperationReverseTest extends TestCase
{
    public function testRawSqlAndDropTableAreNotReversible(): void
    {
        self::assertNull(new RawSql('SELECT 1')->reverse());
        self::assertNull(new DropTable('users')->reverse());
    }

    public function testCreateTableReverseDropsCreatedTable(): void
    {
        $operation = new CreateTable(new Table(
            'users',
            ['id' => new Column('id', ColumnType::Integer, primaryKey: true)],
            ['id'],
        ));

        $reverse = $operation->reverse();

        self::assertInstanceOf(DropTable::class, $reverse);
        self::assertSame('users', $reverse->tableName);
    }

    public function testDropForeignKeyReverseDependsOnForeignKeyPayload(): void
    {
        $foreignKey = new ForeignKey('fk_posts_users', ['user_id'], 'users', ['id']);
        $withPayload = new DropForeignKey('posts', 'fk_posts_users', $foreignKey);
        $withoutPayload = new DropForeignKey('posts', 'fk_posts_users');

        $reverse = $withPayload->reverse();

        self::assertInstanceOf(AddForeignKey::class, $reverse);
        self::assertSame('posts', $reverse->tableName);
        self::assertSame($foreignKey, $reverse->foreignKey);
        self::assertNull($withoutPayload->reverse());
    }
}
