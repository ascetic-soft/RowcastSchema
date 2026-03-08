<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Parser\AttributeSchemaBuilder;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Tests\Fixtures\Entity\InvalidTypeEntity;
use AsceticSoft\RowcastSchema\Tests\Fixtures\Entity\Post;
use AsceticSoft\RowcastSchema\Tests\Fixtures\Entity\User;
use PHPUnit\Framework\TestCase;

final class AttributeSchemaBuilderTest extends TestCase
{
    public function testBuildsSchemaFromAttributedClasses(): void
    {
        $schema = new AttributeSchemaBuilder()->build([
            User::class,
            Post::class,
            \stdClass::class,
        ]);

        $users = $schema->getTable('users');
        self::assertNotNull($users);
        self::assertSame(['id'], $users->primaryKey);
        self::assertArrayHasKey('idx_users_email', $users->indexes);

        $name = $users->getColumn('name');
        self::assertNotNull($name);
        self::assertTrue($name->nullable);

        $status = $users->getColumn('status');
        self::assertNotNull($status);
        self::assertSame(ColumnType::Enum, $status->type);
        self::assertSame(['active', 'banned'], $status->enumValues);

        $priority = $users->getColumn('priority');
        self::assertNotNull($priority);
        self::assertSame(ColumnType::Integer, $priority->type);

        $posts = $schema->getTable('blog_posts');
        self::assertNotNull($posts);
        self::assertArrayHasKey('fk_posts_user', $posts->foreignKeys);
    }

    public function testThrowsWhenColumnTypeCannotBeInferred(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to infer column type for property');

        new AttributeSchemaBuilder()->build([
            InvalidTypeEntity::class,
        ]);
    }
}
