<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
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

    public function testThrowsForUnsupportedAddForeignKeyOnExistingTable(): void
    {
        $platform = new SqlitePlatform(new SqliteTypeMapper());
        $operation = new AddForeignKey('users', new ForeignKey(
            name: 'fk_users_org',
            columns: ['org_id'],
            referenceTable: 'organizations',
            referenceColumns: ['id'],
        ));

        $this->expectException(\RuntimeException::class);
        $platform->toSql($operation);
    }
}
