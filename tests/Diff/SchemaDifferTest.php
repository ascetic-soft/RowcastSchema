<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Diff;

use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\ReferentialAction;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use PHPUnit\Framework\TestCase;

final class SchemaDifferTest extends TestCase
{
    public function testDetectsCreateTableAndAddColumn(): void
    {
        $usersId = new Column('id', ColumnType::Integer, primaryKey: true);

        $from = new Schema([
            'users' => new Table(
                name: 'users',
                columns: [
                    'id' => $usersId,
                ],
                primaryKey: ['id'],
            ),
        ]);

        $to = new Schema([
            'users' => new Table(
                name: 'users',
                columns: [
                    'id' => $usersId,
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

        $operations = new SchemaDiffer()->diff($from, $to);
        self::assertCount(2, $operations);
        self::assertInstanceOf(CreateTable::class, $operations[0]);
        self::assertInstanceOf(AddColumn::class, $operations[1]);
    }

    public function testDetectsAlterDropAndRecreateOperations(): void
    {
        $from = new Schema([
            'users' => new Table(
                name: 'users',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                    'name' => new Column('name', ColumnType::String, length: 50),
                    'legacy_code' => new Column('legacy_code', ColumnType::String),
                ],
                primaryKey: ['id'],
                indexes: [
                    'idx_users_name' => new Index('idx_users_name', ['name']),
                    'idx_users_legacy' => new Index('idx_users_legacy', ['legacy_code']),
                ],
                foreignKeys: [
                    'fk_users_account' => new ForeignKey('fk_users_account', ['id'], 'accounts', ['id']),
                    'fk_users_legacy' => new ForeignKey('fk_users_legacy', ['legacy_code'], 'legacy', ['code']),
                ],
            ),
            'obsolete' => new Table(
                name: 'obsolete',
                columns: ['id' => new Column('id', ColumnType::Integer, primaryKey: true)],
                primaryKey: ['id'],
            ),
        ]);

        $to = new Schema([
            'users' => new Table(
                name: 'users',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                    'name' => new Column('name', ColumnType::String, length: 255),
                    'email' => new Column('email', ColumnType::String, length: 255),
                ],
                primaryKey: ['id'],
                indexes: [
                    'idx_users_name' => new Index('idx_users_name', ['name'], unique: true),
                    'idx_users_email' => new Index('idx_users_email', ['email']),
                ],
                foreignKeys: [
                    'fk_users_account' => new ForeignKey(
                        'fk_users_account',
                        ['id'],
                        'accounts',
                        ['id'],
                        onDelete: ReferentialAction::Cascade,
                    ),
                    'fk_users_profile' => new ForeignKey('fk_users_profile', ['id'], 'profiles', ['user_id']),
                ],
            ),
        ]);

        $operations = new SchemaDiffer()->diff($from, $to);
        $classes = array_map(static fn (object $operation): string => $operation::class, $operations);

        self::assertContains(AlterColumn::class, $classes);
        self::assertContains(AddColumn::class, $classes);
        self::assertContains(DropColumn::class, $classes);
        self::assertContains(DropIndex::class, $classes);
        self::assertContains(AddIndex::class, $classes);
        self::assertContains(DropForeignKey::class, $classes);
        self::assertContains(AddForeignKey::class, $classes);
        self::assertContains(DropTable::class, $classes);
    }

    public function testCreateTableOrderRespectsForeignKeys(): void
    {
        $from = new Schema();
        $to = new Schema([
            'posts' => new Table(
                name: 'posts',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                    'user_id' => new Column('user_id', ColumnType::Integer),
                ],
                primaryKey: ['id'],
                foreignKeys: [
                    'fk_posts_users' => new ForeignKey('fk_posts_users', ['user_id'], 'users', ['id']),
                ],
            ),
            'users' => new Table(
                name: 'users',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                ],
                primaryKey: ['id'],
            ),
        ]);

        $operations = new SchemaDiffer()->diff($from, $to);
        $createTableOperations = array_values(array_filter(
            $operations,
            static fn (object $operation): bool => $operation instanceof CreateTable,
        ));

        self::assertCount(2, $createTableOperations);
        self::assertSame('users', $createTableOperations[0]->table->name);
        self::assertSame('posts', $createTableOperations[1]->table->name);
    }

    public function testDropTableOrderRespectsForeignKeys(): void
    {
        $from = new Schema([
            'posts' => new Table(
                name: 'posts',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                    'user_id' => new Column('user_id', ColumnType::Integer),
                ],
                primaryKey: ['id'],
                foreignKeys: [
                    'fk_posts_users' => new ForeignKey('fk_posts_users', ['user_id'], 'users', ['id']),
                ],
            ),
            'users' => new Table(
                name: 'users',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                ],
                primaryKey: ['id'],
            ),
        ]);

        $to = new Schema();

        $operations = new SchemaDiffer()->diff($from, $to);
        $dropTableOperations = array_values(array_filter(
            $operations,
            static fn (object $operation): bool => $operation instanceof DropTable,
        ));

        self::assertCount(2, $dropTableOperations);
        self::assertSame('posts', $dropTableOperations[0]->tableName);
        self::assertSame('users', $dropTableOperations[1]->tableName);
    }

    public function testCircularForeignKeysExtractedAsAddForeignKeys(): void
    {
        $from = new Schema();
        $to = new Schema([
            'a' => new Table(
                name: 'a',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                    'b_id' => new Column('b_id', ColumnType::Integer),
                ],
                primaryKey: ['id'],
                foreignKeys: [
                    'fk_a_b' => new ForeignKey('fk_a_b', ['b_id'], 'b', ['id']),
                ],
            ),
            'b' => new Table(
                name: 'b',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                    'a_id' => new Column('a_id', ColumnType::Integer),
                ],
                primaryKey: ['id'],
                foreignKeys: [
                    'fk_b_a' => new ForeignKey('fk_b_a', ['a_id'], 'a', ['id']),
                ],
            ),
        ]);

        $operations = new SchemaDiffer()->diff($from, $to);

        self::assertInstanceOf(CreateTable::class, $operations[0]);
        self::assertInstanceOf(CreateTable::class, $operations[1]);
        self::assertSame([], $operations[0]->table->foreignKeys);
        self::assertSame([], $operations[1]->table->foreignKeys);

        $addForeignKeyOperations = array_values(array_filter(
            $operations,
            static fn (object $operation): bool => $operation instanceof AddForeignKey,
        ));

        self::assertCount(2, $addForeignKeyOperations);
        self::assertSame('a', $addForeignKeyOperations[0]->tableName);
        self::assertSame('b', $addForeignKeyOperations[1]->tableName);
    }

    public function testSelfReferencingForeignKeyDoesNotAffectOrder(): void
    {
        $from = new Schema();
        $to = new Schema([
            'users' => new Table(
                name: 'users',
                columns: [
                    'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                    'manager_id' => new Column('manager_id', ColumnType::Integer, nullable: true),
                ],
                primaryKey: ['id'],
                foreignKeys: [
                    'fk_users_manager' => new ForeignKey('fk_users_manager', ['manager_id'], 'users', ['id']),
                ],
            ),
        ]);

        $operations = new SchemaDiffer()->diff($from, $to);
        self::assertCount(1, $operations);
        self::assertInstanceOf(CreateTable::class, $operations[0]);
        self::assertArrayHasKey('fk_users_manager', $operations[0]->table->foreignKeys);
    }
}
