<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropIndex;
use AsceticSoft\RowcastSchema\Platform\PostgresPlatform;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\TypeMapper\PostgresTypeMapper;
use PHPUnit\Framework\TestCase;

final class PostgresPlatformTest extends TestCase
{
    public function testSupportsDdlTransactionsIsTrue(): void
    {
        self::assertTrue(new PostgresPlatform(new PostgresTypeMapper())->supportsDdlTransactions());
    }

    public function testGeneratesSqlForBasicOperations(): void
    {
        $platform = new PostgresPlatform(new PostgresTypeMapper());

        $addColumn = new AddColumn('users', new Column('is_active', ColumnType::Boolean, default: true));
        self::assertSame(['ALTER TABLE "users" ADD COLUMN "is_active" BOOLEAN NOT NULL DEFAULT 1'], $platform->toSql($addColumn));

        $addIndex = new AddIndex('users', new Index('idx_users_email', ['email']));
        self::assertSame(['CREATE INDEX "idx_users_email" ON "users" ("email")'], $platform->toSql($addIndex));

        $addForeignKey = new AddForeignKey('users', new ForeignKey('fk_users_account', ['id'], 'accounts', ['id']));
        self::assertSame(
            ['ALTER TABLE "users" ADD CONSTRAINT "fk_users_account" FOREIGN KEY ("id") REFERENCES "accounts" ("id")'],
            $platform->toSql($addForeignKey),
        );

        self::assertSame(['DROP INDEX "idx_users_email"'], $platform->toSql(new DropIndex('users', 'idx_users_email')));
        self::assertSame(
            ['ALTER TABLE "users" DROP CONSTRAINT "fk_users_account"'],
            $platform->toSql(new DropForeignKey('users', 'fk_users_account')),
        );
    }

    public function testGeneratesAlterColumnSqlWhenOldColumnIsMissing(): void
    {
        $platform = new PostgresPlatform(new PostgresTypeMapper());
        $operation = new AlterColumn(
            'users',
            'name',
            new Column('full_name', ColumnType::String, length: 200, nullable: true, default: 'guest'),
        );

        self::assertSame([
            'ALTER TABLE "users" RENAME COLUMN "name" TO "full_name"',
            'ALTER TABLE "users" ALTER COLUMN "full_name" TYPE VARCHAR(200)',
            'ALTER TABLE "users" ALTER COLUMN "full_name" DROP NOT NULL',
            'ALTER TABLE "users" ALTER COLUMN "full_name" SET DEFAULT \'guest\'',
        ], $platform->toSql($operation));
    }

    public function testGeneratesAlterColumnSqlUsingDiffBetweenOldAndNew(): void
    {
        $platform = new PostgresPlatform(new PostgresTypeMapper());
        $operation = new AlterColumn(
            'users',
            'name',
            new Column('name', ColumnType::String, length: 255, nullable: false, default: null),
            new Column('name', ColumnType::String, length: 150, nullable: true, default: 'guest'),
        );

        self::assertSame([
            'ALTER TABLE "users" ALTER COLUMN "name" TYPE VARCHAR(255)',
            'ALTER TABLE "users" ALTER COLUMN "name" SET NOT NULL',
            'ALTER TABLE "users" ALTER COLUMN "name" DROP DEFAULT',
        ], $platform->toSql($operation));
    }

    public function testThrowsWhenDefaultValueIsNotScalar(): void
    {
        $platform = new PostgresPlatform(new PostgresTypeMapper());
        $operation = new AlterColumn(
            'users',
            'meta',
            new Column('meta', ColumnType::Json, default: ['x' => 1]),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Default column value must be scalar.');
        $platform->toSql($operation);
    }
}
