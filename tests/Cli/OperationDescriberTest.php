<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli;

use AsceticSoft\RowcastSchema\Cli\OperationDescriber;
use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Diff\Operation\RawSql;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\Table;
use PHPUnit\Framework\TestCase;

final class OperationDescriberTest extends TestCase
{
    public function testDescribeSupportsAllKnownOperationsAndFallback(): void
    {
        $describer = new OperationDescriber();
        $table = new Table(
            'posts',
            ['id' => new Column('id', ColumnType::Integer, primaryKey: true, autoIncrement: true)],
            ['id'],
            ['idx_posts_id' => new Index('idx_posts_id', ['id'], unique: true)],
            ['fk_posts_user' => new ForeignKey('fk_posts_user', ['id'], 'users', ['id'])],
        );

        self::assertSame('+ Create table "posts" (1 columns, 1 indexes, 1 FKs)', $describer->describe(new CreateTable($table)));
        self::assertSame('- Drop table "posts"', $describer->describe(new DropTable('posts')));
        self::assertSame(
            '+ Add column "posts"."embedding" (vector(1536))',
            $describer->describe(new AddColumn('posts', new Column('embedding', ColumnType::Text, databaseType: 'vector(1536)'))),
        );
        self::assertSame('- Drop column "posts"."legacy_col"', $describer->describe(new DropColumn('posts', 'legacy_col')));
        self::assertSame(
            '~ Alter column "posts"."title" (string(120) -> string(255))',
            $describer->describe(new AlterColumn(
                'posts',
                'title',
                new Column('title', ColumnType::String, length: 255),
                new Column('title', ColumnType::String, length: 120),
            )),
        );
        self::assertSame(
            '~ Alter column "posts"."content" (unknown -> text)',
            $describer->describe(new AlterColumn(
                'posts',
                'content',
                new Column('content', ColumnType::Text),
                null,
            )),
        );
        self::assertSame(
            '+ Add index "posts"."idx_posts_title" (title) unique',
            $describer->describe(new AddIndex('posts', new Index('idx_posts_title', ['title'], unique: true))),
        );
        self::assertSame('- Drop index "posts"."idx_posts_title"', $describer->describe(new DropIndex('posts', 'idx_posts_title')));
        self::assertSame(
            '+ Add FK "posts"."fk_posts_user" -> users(id)',
            $describer->describe(new AddForeignKey(
                'posts',
                new ForeignKey('fk_posts_user', ['user_id'], 'users', ['id']),
            )),
        );
        self::assertSame(
            '- Drop FK "posts"."fk_posts_user"',
            $describer->describe(new DropForeignKey('posts', 'fk_posts_user')),
        );

        $longSql = "SELECT *\nFROM posts WHERE title LIKE 'abcdef%' AND body LIKE 'qwerty%' ORDER BY id DESC LIMIT 100";
        self::assertSame(
            "~ Execute raw SQL (SELECT * FROM posts WHERE title LIKE 'abcdef%' AND body LIKE 'qwerty%' ORDER ...)",
            $describer->describe(new RawSql($longSql)),
        );
        self::assertSame(
            '~ AsceticSoft\RowcastSchema\Tests\Cli\DummyOperation',
            $describer->describe(new DummyOperation()),
        );
    }

    public function testDescribeDetailsForSupportedOperationTypes(): void
    {
        $describer = new OperationDescriber();
        $table = new Table(
            'events',
            [
                'id' => new Column('id', ColumnType::Integer, primaryKey: true, autoIncrement: true),
                'amount' => new Column('amount', ColumnType::Decimal, precision: 10, scale: 2, default: 0.0),
            ],
        );

        self::assertSame(
            [
                'id integer [PK, AI]',
                'amount decimal(10,2) [default=0.0]',
            ],
            $describer->describeDetails(new CreateTable($table)),
        );
        self::assertSame(
            ['emb vector(1536) [nullable]'],
            $describer->describeDetails(new AddColumn(
                'events',
                new Column('emb', ColumnType::Text, nullable: true, databaseType: 'vector(1536)'),
            )),
        );
        self::assertSame(
            ["title string(200) [default='n/a']"],
            $describer->describeDetails(new AlterColumn(
                'events',
                'title',
                new Column('title', ColumnType::String, length: 200, default: 'n/a'),
            )),
        );
        self::assertSame(
            ['columns: (user_id), references: users(id), onDelete=CASCADE, onUpdate=RESTRICT'],
            $describer->describeDetails(new AddForeignKey(
                'events',
                new ForeignKey('fk_events_user', ['user_id'], 'users', ['id'], onDelete: 'CASCADE', onUpdate: 'RESTRICT'),
            )),
        );
        self::assertSame(
            ['columns: (created_at), unique'],
            $describer->describeDetails(new AddIndex('events', new Index('idx_created_at', ['created_at'], unique: true))),
        );
        self::assertSame([], $describer->describeDetails(new DropTable('events')));
    }

    public function testDescribeSummaryBuildsGroupedOutput(): void
    {
        $describer = new OperationDescriber();

        self::assertSame('No operations.', $describer->describeSummary([]));

        $summary = $describer->describeSummary([
            new CreateTable(new Table('t1', ['id' => new Column('id', ColumnType::Integer)])),
            new AddColumn('t1', new Column('name', ColumnType::String, length: 120)),
            new AddColumn('t1', new Column('active', ColumnType::Boolean)),
            new RawSql('VACUUM'),
            new RawSql('ANALYZE'),
        ]);

        self::assertSame('1 tables created, 2 columns added, 2 raw SQL statements', $summary);
    }
}

final readonly class DummyOperation implements OperationInterface
{
}
