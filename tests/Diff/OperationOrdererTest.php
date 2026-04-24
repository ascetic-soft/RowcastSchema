<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Diff;

use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Diff\Operation\RawSql;
use AsceticSoft\RowcastSchema\Diff\OperationOrderer;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use PHPUnit\Framework\TestCase;

final class OperationOrdererTest extends TestCase
{
    public function testReturnsEmptyListForEmptyOperations(): void
    {
        $orderer = new OperationOrderer();

        self::assertSame([], $orderer->order([], new Schema()));
    }

    public function testPreservesOtherOperationsWhenNoCreateOrDropExist(): void
    {
        $orderer = new OperationOrderer();
        $operation = new RawSql('SELECT 1');

        $ordered = $orderer->order([$operation], new Schema());

        self::assertCount(1, $ordered);
        self::assertSame($operation, $ordered[0]);
    }

    public function testHandlesDropTableMissingFromSourceSchema(): void
    {
        $orderer = new OperationOrderer();
        $drop = new DropTable('missing_table');

        $ordered = $orderer->order([$drop], new Schema());

        self::assertCount(1, $ordered);
        self::assertSame($drop, $ordered[0]);
    }

    public function testKeepsCreateTableWithoutDependenciesBeforeOtherOperations(): void
    {
        $orderer = new OperationOrderer();
        $create = new CreateTable(new Table(
            'users',
            ['id' => new Column('id', ColumnType::Integer, primaryKey: true)],
            ['id'],
        ));
        $raw = new RawSql('SELECT 1');

        $ordered = $orderer->order([$raw, $create], new Schema());

        self::assertSame($create, $ordered[0]);
        self::assertSame($raw, $ordered[1]);
    }

    public function testOrdersCreateTablesByForeignKeyDependencies(): void
    {
        $orderer = new OperationOrderer();
        $users = new CreateTable(new Table(
            'users',
            ['id' => new Column('id', ColumnType::Integer, primaryKey: true)],
            ['id'],
        ));
        $posts = new CreateTable(new Table(
            'posts',
            [
                'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                'user_id' => new Column('user_id', ColumnType::Integer),
            ],
            ['id'],
            foreignKeys: [
                'fk_posts_users' => new ForeignKey('fk_posts_users', ['user_id'], 'users', ['id']),
            ],
        ));

        $ordered = $orderer->order([$posts, $users], new Schema());

        self::assertSame('users', $ordered[0]->table->name);
        self::assertSame('posts', $ordered[1]->table->name);
    }

    public function testExtractsCyclicForeignKeysIntoFollowUpOperations(): void
    {
        $orderer = new OperationOrderer();
        $users = new CreateTable(new Table(
            'users',
            [
                'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                'manager_post_id' => new Column('manager_post_id', ColumnType::Integer, nullable: true),
            ],
            ['id'],
            foreignKeys: [
                'fk_users_posts' => new ForeignKey('fk_users_posts', ['manager_post_id'], 'posts', ['id']),
            ],
        ));
        $posts = new CreateTable(new Table(
            'posts',
            [
                'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                'author_id' => new Column('author_id', ColumnType::Integer),
            ],
            ['id'],
            foreignKeys: [
                'fk_posts_users' => new ForeignKey('fk_posts_users', ['author_id'], 'users', ['id']),
            ],
        ));

        $ordered = $orderer->order([$users, $posts], new Schema());

        self::assertCount(4, $ordered);
        self::assertInstanceOf(CreateTable::class, $ordered[0]);
        self::assertInstanceOf(CreateTable::class, $ordered[1]);
        self::assertInstanceOf(AddForeignKey::class, $ordered[2]);
        self::assertInstanceOf(AddForeignKey::class, $ordered[3]);
        self::assertCount(0, $ordered[0]->table->foreignKeys);
        self::assertCount(0, $ordered[1]->table->foreignKeys);
        self::assertSame('fk_users_posts', $ordered[2]->foreignKey->name);
        self::assertSame('fk_posts_users', $ordered[3]->foreignKey->name);
    }

    public function testKeepsSelfReferencingForeignKeysInline(): void
    {
        $orderer = new OperationOrderer();
        $categories = new CreateTable(new Table(
            'categories',
            [
                'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                'parent_id' => new Column('parent_id', ColumnType::Integer, nullable: true),
            ],
            ['id'],
            foreignKeys: [
                'fk_categories_parent' => new ForeignKey('fk_categories_parent', ['parent_id'], 'categories', ['id']),
            ],
        ));

        $ordered = $orderer->order([$categories], new Schema());

        self::assertCount(1, $ordered);
        self::assertInstanceOf(CreateTable::class, $ordered[0]);
        self::assertCount(1, $ordered[0]->table->foreignKeys);
    }

    public function testOrdersDropTablesByReverseDependencyOrder(): void
    {
        $orderer = new OperationOrderer();
        $users = new Table(
            'users',
            ['id' => new Column('id', ColumnType::Integer, primaryKey: true)],
            ['id'],
        );
        $posts = new Table(
            'posts',
            [
                'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                'user_id' => new Column('user_id', ColumnType::Integer),
            ],
            ['id'],
            foreignKeys: [
                'fk_posts_users' => new ForeignKey('fk_posts_users', ['user_id'], 'users', ['id']),
            ],
        );

        $ordered = $orderer->order([
            new DropTable('users'),
            new DropTable('posts'),
        ], new Schema(['users' => $users, 'posts' => $posts]));

        self::assertSame('posts', $ordered[0]->tableName);
        self::assertSame('users', $ordered[1]->tableName);
    }
}
