<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Parser\AttributeSchemaParser;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use PHPUnit\Framework\TestCase;

final class AttributeSchemaParserTest extends TestCase
{
    public function testParsesSchemaFromEntityDirectory(): void
    {
        $path = dirname(__DIR__) . '/Fixtures/EntityValid';

        $schema = new AttributeSchemaParser()->parse($path);

        self::assertTrue($schema->hasTable('users'));
        self::assertTrue($schema->hasTable('blog_posts'));

        $users = $schema->getTable('users');
        self::assertNotNull($users);
        $status = $users->getColumn('status');
        self::assertNotNull($status);
        self::assertSame(ColumnType::Enum, $status->type);
    }
}
