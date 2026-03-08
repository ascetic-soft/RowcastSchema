<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\SchemaBuilder;

use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;
use PHPUnit\Framework\TestCase;

final class SchemaBuilderTest extends TestCase
{
    public function testCollectsOperationsOfAllSupportedKinds(): void
    {
        $builder = new SchemaBuilder();
        $builder
            ->createTable('users', static function ($table): void {
                $table->column('id', ColumnType::Integer)->primaryKey();
            })
            ->dropTable('legacy_users')
            ->addColumn('users', new Column('email', ColumnType::String))
            ->dropColumn('users', 'nickname')
            ->alterColumn('users', 'email', new Column('email', ColumnType::String, length: 320))
            ->addIndex('users', 'idx_users_email', ['email'], unique: true)
            ->dropIndex('users', 'idx_users_legacy')
            ->addForeignKey('users', 'fk_users_account', ['id'], 'accounts', ['id'], 'CASCADE', 'RESTRICT')
            ->dropForeignKey('users', 'fk_users_old');

        $operations = $builder->getOperations();
        $classes = array_map(static fn (object $operation): string => $operation::class, $operations);

        self::assertSame([
            CreateTable::class,
            DropTable::class,
            AddColumn::class,
            DropColumn::class,
            AlterColumn::class,
            AddIndex::class,
            DropIndex::class,
            AddForeignKey::class,
            DropForeignKey::class,
        ], $classes);
    }

    public function testResetClearsOperations(): void
    {
        $builder = new SchemaBuilder();
        $builder->dropTable('legacy_users');

        self::assertCount(1, $builder->getOperations());

        $builder->reset();

        self::assertSame([], $builder->getOperations());
    }
}
