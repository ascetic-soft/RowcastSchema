<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Platform\MysqlPlatform;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\Table;
use AsceticSoft\RowcastSchema\TypeMapper\MysqlTypeMapper;
use PHPUnit\Framework\TestCase;

final class MysqlPlatformTest extends TestCase
{
    public function testSupportsDdlTransactionsIsFalse(): void
    {
        self::assertFalse(new MysqlPlatform(new MysqlTypeMapper())->supportsDdlTransactions());
    }

    public function testGeneratesCreateTableSqlWithIndexesAndForeignKeys(): void
    {
        $platform = new MysqlPlatform(new MysqlTypeMapper());
        $table = new Table(
            name: 'users',
            columns: [
                'id' => new Column('id', ColumnType::Integer, primaryKey: true, autoIncrement: true),
                'email' => new Column('email', ColumnType::String, length: 255, nullable: false),
            ],
            primaryKey: ['id'],
            indexes: [
                'idx_users_email' => new Index('idx_users_email', ['email'], unique: true),
            ],
            foreignKeys: [
                'fk_users_account' => new ForeignKey('fk_users_account', ['id'], 'accounts', ['id'], 'cascade', 'restrict'),
            ],
        );

        $sql = $platform->toSql(new CreateTable($table));

        self::assertStringContainsString('CREATE TABLE `users`', $sql[0]);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $sql[0]);
        self::assertSame('CREATE UNIQUE INDEX `idx_users_email` ON `users` (`email`)', $sql[1]);
        self::assertSame(
            'ALTER TABLE `users` ADD CONSTRAINT `fk_users_account` FOREIGN KEY (`id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT',
            $sql[2],
        );
    }

    public function testGeneratesSqlForSingleOperations(): void
    {
        $platform = new MysqlPlatform(new MysqlTypeMapper());

        self::assertSame(['DROP TABLE `users`'], $platform->toSql(new DropTable('users')));

        $addColumn = new AddColumn('users', new Column('visits', ColumnType::Integer, default: 1, nullable: false));
        self::assertSame(['ALTER TABLE `users` ADD COLUMN `visits` INT NOT NULL DEFAULT 1'], $platform->toSql($addColumn));

        $addIndex = new AddIndex('users', new Index('idx_users_email', ['email']));
        self::assertSame(['CREATE INDEX `idx_users_email` ON `users` (`email`)'], $platform->toSql($addIndex));

        self::assertSame(['DROP INDEX `idx_users_email` ON `users`'], $platform->toSql(new DropIndex('users', 'idx_users_email')));
        self::assertSame(
            ['ALTER TABLE `users` DROP FOREIGN KEY `fk_users_account`'],
            $platform->toSql(new DropForeignKey('users', 'fk_users_account')),
        );
    }

    public function testGeneratesAlterColumnSqlForRenameAndModify(): void
    {
        $platform = new MysqlPlatform(new MysqlTypeMapper());

        $rename = new AlterColumn(
            'users',
            'name',
            new Column('full_name', ColumnType::String, length: 200, nullable: true),
        );
        self::assertSame(
            ['ALTER TABLE `users` CHANGE COLUMN `name` `full_name` VARCHAR(200)'],
            $platform->toSql($rename),
        );

        $modify = new AlterColumn(
            'users',
            'name',
            new Column('name', ColumnType::String, length: 150, default: "Bob's"),
        );
        self::assertSame(
            ['ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(150) NOT NULL DEFAULT \'Bob\\\'s\''],
            $platform->toSql($modify),
        );
    }
}
