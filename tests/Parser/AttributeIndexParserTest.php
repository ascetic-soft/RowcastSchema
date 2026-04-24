<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Attribute\ForeignKey as ForeignKeyAttribute;
use AsceticSoft\RowcastSchema\Attribute\Index as IndexAttribute;
use AsceticSoft\RowcastSchema\Parser\AttributeIndexParser;
use AsceticSoft\RowcastSchema\Schema\ReferentialAction;
use PHPUnit\Framework\TestCase;

final class AttributeIndexParserTest extends TestCase
{
    public function testParsesIndexWithExplicitColumns(): void
    {
        $parser = new AttributeIndexParser();

        $index = $parser->parseIndex(new IndexAttribute('idx_users_email', ['email'], true));

        self::assertSame('idx_users_email', $index->name);
        self::assertSame(['email'], $index->columns);
        self::assertTrue($index->unique);
    }

    public function testParsesIndexUsingDefaultColumn(): void
    {
        $parser = new AttributeIndexParser();

        $index = $parser->parseIndex(new IndexAttribute('idx_users_email'), 'email');

        self::assertSame(['email'], $index->columns);
    }

    public function testThrowsWhenIndexColumnsMissingWithoutDefaultColumn(): void
    {
        $parser = new AttributeIndexParser();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Index "idx_users_email" columns must be list.');
        $parser->parseIndex(new IndexAttribute('idx_users_email'));
    }

    public function testThrowsWhenIndexColumnsContainNonString(): void
    {
        $parser = new AttributeIndexParser();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Index "idx_users_email" columns must be list.');
        $parser->parseIndex(new IndexAttribute('idx_users_email', ['email', 123]));
    }

    public function testParsesForeignKeyWithExplicitColumns(): void
    {
        $parser = new AttributeIndexParser();

        $foreignKey = $parser->parseForeignKey(new ForeignKeyAttribute(
            name: 'fk_posts_user',
            referenceTable: 'users',
            referenceColumns: ['id'],
            columns: ['user_id'],
            onDelete: ReferentialAction::Cascade,
            onUpdate: ReferentialAction::Restrict,
        ));

        self::assertSame('fk_posts_user', $foreignKey->name);
        self::assertSame(['user_id'], $foreignKey->columns);
        self::assertSame('users', $foreignKey->referenceTable);
        self::assertSame(['id'], $foreignKey->referenceColumns);
        self::assertSame(ReferentialAction::Cascade, $foreignKey->onDelete);
        self::assertSame(ReferentialAction::Restrict, $foreignKey->onUpdate);
    }

    public function testParsesForeignKeyUsingDefaultColumn(): void
    {
        $parser = new AttributeIndexParser();

        $foreignKey = $parser->parseForeignKey(new ForeignKeyAttribute(
            name: 'fk_posts_user',
            referenceTable: 'users',
            referenceColumns: ['id'],
        ), 'user_id');

        self::assertSame(['user_id'], $foreignKey->columns);
    }

    public function testThrowsWhenForeignKeyColumnsMissingWithoutDefaultColumn(): void
    {
        $parser = new AttributeIndexParser();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign key "fk_posts_user" columns must be lists.');
        $parser->parseForeignKey(new ForeignKeyAttribute(
            name: 'fk_posts_user',
            referenceTable: 'users',
            referenceColumns: ['id'],
        ));
    }

    public function testThrowsWhenForeignKeyColumnsContainNonString(): void
    {
        $parser = new AttributeIndexParser();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign key "fk_posts_user" columns must be lists.');
        $parser->parseForeignKey(new ForeignKeyAttribute(
            name: 'fk_posts_user',
            referenceTable: 'users',
            referenceColumns: ['id'],
            columns: ['user_id', 123],
        ));
    }

    public function testThrowsWhenForeignKeyReferenceColumnsContainNonString(): void
    {
        $parser = new AttributeIndexParser();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign key "fk_posts_user" columns must be lists.');
        $parser->parseForeignKey(new ForeignKeyAttribute(
            name: 'fk_posts_user',
            referenceTable: 'users',
            referenceColumns: ['id', 123],
            columns: ['user_id'],
        ));
    }
}
