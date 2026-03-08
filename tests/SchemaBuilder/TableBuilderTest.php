<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\SchemaBuilder;

use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\SchemaBuilder\TableBuilder;
use PHPUnit\Framework\TestCase;

final class TableBuilderTest extends TestCase
{
    public function testResolvesColumnTypesFromEnumsAliasesAndCustomTypes(): void
    {
        $builder = new TableBuilder('events');
        $builder->column('id', ColumnType::Integer)->primaryKey();
        $builder->column('payload', 'jsonb');
        $builder->column('title', 'citext');

        $table = $builder->toTable();

        $payload = $table->getColumn('payload');
        self::assertNotNull($payload);
        self::assertSame(ColumnType::Json, $payload->type);
        self::assertNull($payload->databaseType);

        $title = $table->getColumn('title');
        self::assertNotNull($title);
        self::assertSame(ColumnType::Text, $title->type);
        self::assertSame('citext', $title->databaseType);
    }

    public function testUsesExplicitPrimaryKeyWhenProvided(): void
    {
        $builder = new TableBuilder('users');
        $builder->column('id', ColumnType::Integer)->primaryKey();
        $builder->column('email', ColumnType::String);
        $builder->primaryKey(['email']);

        $table = $builder->toTable();

        self::assertSame(['email'], $table->primaryKey);
    }

    public function testBuildsIndexesAndForeignKeys(): void
    {
        $builder = new TableBuilder('posts');
        $builder->column('id', ColumnType::Integer)->primaryKey();
        $builder->column('user_id', ColumnType::Integer);
        $builder->index('idx_posts_user_id', ['user_id']);
        $builder->foreignKey('fk_posts_user_id', ['user_id'], 'users', ['id'], 'CASCADE', 'RESTRICT');

        $table = $builder->toTable();

        self::assertArrayHasKey('idx_posts_user_id', $table->indexes);
        self::assertArrayHasKey('fk_posts_user_id', $table->foreignKeys);
    }
}
