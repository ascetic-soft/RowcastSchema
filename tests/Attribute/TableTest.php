<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Attribute;

use AsceticSoft\RowcastSchema\Attribute\Table;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    public function testStoresConstructorArguments(): void
    {
        $attribute = new Table(
            name: 'users',
            engine: 'InnoDB',
            charset: 'utf8mb4',
            collation: 'utf8mb4_unicode_ci',
        );

        self::assertSame('users', $attribute->name);
        self::assertSame('InnoDB', $attribute->engine);
        self::assertSame('utf8mb4', $attribute->charset);
        self::assertSame('utf8mb4_unicode_ci', $attribute->collation);
    }
}
