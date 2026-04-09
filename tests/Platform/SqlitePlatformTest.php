<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Platform\SqlitePlatform;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\Table;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;
use PHPUnit\Framework\TestCase;

final class SqlitePlatformTest extends TestCase
{
    public function testGeneratesCreateTableSql(): void
    {
        $platform = new SqlitePlatform(new SqliteTypeMapper());
        $operation = new CreateTable(new Table(
            name: 'users',
            columns: [
                'id' => new Column('id', ColumnType::Integer, primaryKey: true),
                'email' => new Column('email', ColumnType::String, length: 255),
            ],
            primaryKey: ['id'],
        ));

        $sql = $platform->toSql($operation);

        self::assertNotEmpty($sql);
        self::assertStringContainsString('CREATE TABLE "users"', $sql[0]);
        self::assertStringContainsString('"id" INTEGER', $sql[0]);
    }

    public function testReturnEmptyForRebuildRequiredAddForeignKey(): void
    {
        $platform = new SqlitePlatform(new SqliteTypeMapper());
        $operation = new AddForeignKey('users', new ForeignKey(
            name: 'fk_users_org',
            columns: ['org_id'],
            referenceTable: 'organizations',
            referenceColumns: ['id'],
        ));

        self::assertSame([], $platform->toSql($operation));
    }

    public function testGeneratesSqlForBasicOperations(): void
    {
        $platform = new SqlitePlatform(new SqliteTypeMapper());

        self::assertSame(['DROP TABLE "users"'], $platform->toSql(new DropTable('users')));

        $addColumnSql = $platform->toSql(new AddColumn('users', new Column('visits', ColumnType::Integer, default: 1)));
        self::assertSame(['ALTER TABLE "users" ADD COLUMN "visits" INTEGER NOT NULL DEFAULT 1'], $addColumnSql);

        self::assertSame(['ALTER TABLE "users" DROP COLUMN "legacy"'], $platform->toSql(new DropColumn('users', 'legacy')));

        $addIndexSql = $platform->toSql(new AddIndex('users', new \AsceticSoft\RowcastSchema\Schema\Index('idx_users_email', ['email'], true)));
        self::assertSame(['CREATE UNIQUE INDEX "idx_users_email" ON "users" ("email")'], $addIndexSql);

        self::assertSame(['DROP INDEX "idx_users_email"'], $platform->toSql(new DropIndex('users', 'idx_users_email')));
    }

    public function testCompilesDefaultsAndEscapingInCreateTable(): void
    {
        $platform = new SqlitePlatform(new SqliteTypeMapper());
        $operation = new CreateTable(new Table(
            name: 'events',
            columns: [
                'is_active' => new Column('is_active', ColumnType::Boolean, default: true),
                'ratio' => new Column('ratio', ColumnType::Double, default: 1.5),
                'created_at' => new Column('created_at', ColumnType::Timestamp, default: 'CURRENT_TIMESTAMP'),
                'title' => new Column('title', ColumnType::String, default: "Bob's post"),
            ],
        ));

        $sql = $platform->toSql($operation);

        self::assertStringContainsString('"is_active" INTEGER NOT NULL DEFAULT 1', $sql[0]);
        self::assertStringContainsString('"ratio" REAL NOT NULL DEFAULT 1.5', $sql[0]);
        self::assertStringContainsString('"created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP', $sql[0]);
        self::assertStringContainsString('"title" TEXT NOT NULL DEFAULT \'Bob\\\'s post\'', $sql[0]);
    }

    public function testReturnEmptyForRebuildRequiredDropForeignKey(): void
    {
        $platform = new SqlitePlatform(new SqliteTypeMapper());

        self::assertSame([], $platform->toSql(new DropForeignKey('users', 'fk_users_org')));
    }

    public function testReturnEmptyForRebuildRequiredAlterColumn(): void
    {
        $platform = new SqlitePlatform(new SqliteTypeMapper());

        self::assertSame([], $platform->toSql(new AlterColumn('users', 'email', new Column('email', ColumnType::String, length: 320))));
    }

    public function testThrowsWhenDefaultValueIsNotScalar(): void
    {
        $platform = new SqlitePlatform(new SqliteTypeMapper());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Default column value must be scalar.');

        $platform->toSql(new AddColumn('users', new Column('meta', ColumnType::Json, default: ['a' => 'b'])));
    }
}
