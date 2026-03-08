<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Parser\NamingStrategy;
use PHPUnit\Framework\TestCase;

final class NamingStrategyTest extends TestCase
{
    public function testConvertsPropertyNameToSnakeCase(): void
    {
        $strategy = new NamingStrategy();

        self::assertSame('user_id', $strategy->propertyToColumnName('userId'));
        self::assertSame('created_at', $strategy->propertyToColumnName('createdAt'));
    }

    public function testConvertsClassNameToPluralTableName(): void
    {
        $strategy = new NamingStrategy();

        self::assertSame('users', $strategy->classToTableName('User'));
        self::assertSame('user_profiles', $strategy->classToTableName('UserProfile'));
    }
}
