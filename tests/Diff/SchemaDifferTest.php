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
        self::assertInstanceOf(AddColumn::class, $operations[0]);
        self::assertInstanceOf(CreateTable::class, $operations[1]);
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
                    'fk_users_account' => new ForeignKey('fk_users_account', ['id'], 'accounts', ['id'], onDelete: 'CASCADE'),
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
}
