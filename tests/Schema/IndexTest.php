<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Schema;

use AsceticSoft\RowcastSchema\Schema\Index;
use PHPUnit\Framework\TestCase;

final class IndexTest extends TestCase
{
    public function testCreatesUniqueIndex(): void
    {
        $index = new Index('idx_users_email', ['email'], unique: true);

        self::assertSame('idx_users_email', $index->name);
        self::assertSame(['email'], $index->columns);
        self::assertTrue($index->unique);
    }

    public function testThrowsWhenNameIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Index name cannot be empty.');

        new Index('', ['email']);
    }

    public function testThrowsWhenColumnsAreEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Index must contain at least one column.');

        new Index('idx_users_email', []);
    }
}
