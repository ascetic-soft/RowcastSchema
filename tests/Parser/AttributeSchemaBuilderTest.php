<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Parser\AttributeSchemaBuilder;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Tests\Fixtures\Entity\Article;
use AsceticSoft\RowcastSchema\Tests\Fixtures\Entity\InvalidTypeEntity;
use AsceticSoft\RowcastSchema\Tests\Fixtures\Entity\OzonCategoryEmbedding;
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
            Article::class,
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

        $articles = $schema->getTable('articles');
        self::assertNotNull($articles);

        $published = $articles->getColumn('published');
        self::assertNotNull($published);
        self::assertFalse($published->default);

        $featured = $articles->getColumn('featured');
        self::assertNotNull($featured);
        self::assertTrue($featured->default);
    }

    public function testThrowsWhenColumnTypeCannotBeInferred(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to infer column type for property');

        new AttributeSchemaBuilder()->build([
            InvalidTypeEntity::class,
        ]);
    }

    public function testPreservesExplicitDatabaseTypeFromAttribute(): void
    {
        $schema = new AttributeSchemaBuilder()->build([
            OzonCategoryEmbedding::class,
        ]);

        $categories = $schema->getTable('ozon_categories');
        self::assertNotNull($categories);

        $gigachat = $categories->getColumn('gigachat_embedding');
        self::assertNotNull($gigachat);
        self::assertSame(ColumnType::Text, $gigachat->type);
        self::assertSame('vector(1024)', $gigachat->databaseType);

        $openai = $categories->getColumn('openai_embedding');
        self::assertNotNull($openai);
        self::assertSame(ColumnType::Text, $openai->type);
        self::assertSame('vector(1536)', $openai->databaseType);
    }
}
