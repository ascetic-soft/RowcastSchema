<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Schema;

use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use PHPUnit\Framework\TestCase;

final class ForeignKeyTest extends TestCase
{
    public function testCreatesForeignKey(): void
    {
        $foreignKey = new ForeignKey(
            name: 'fk_posts_user_id',
            columns: ['user_id'],
            referenceTable: 'users',
            referenceColumns: ['id'],
            onDelete: 'CASCADE',
        );

        self::assertSame('fk_posts_user_id', $foreignKey->name);
        self::assertSame(['user_id'], $foreignKey->columns);
        self::assertSame(['id'], $foreignKey->referenceColumns);
        self::assertSame('CASCADE', $foreignKey->onDelete);
    }

    public function testThrowsWhenNameIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign key name cannot be empty.');

        new ForeignKey('', ['user_id'], 'users', ['id']);
    }

    public function testThrowsWhenColumnsAreEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign key columns cannot be empty.');

        new ForeignKey('fk_posts_user_id', [], 'users', ['id']);
    }

    public function testThrowsWhenColumnCountDoesNotMatchReferenceColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Foreign key columns and reference columns size mismatch.');

        new ForeignKey('fk_posts_user_id', ['tenant_id', 'user_id'], 'users', ['id']);
    }
}
