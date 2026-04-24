<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Diff;

use AsceticSoft\RowcastSchema\Diff\ForeignKeyComparator;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\ReferentialAction;
use PHPUnit\Framework\TestCase;

final class ForeignKeyComparatorTest extends TestCase
{
    public function testTreatsNoActionVariantsAsEquivalent(): void
    {
        $comparator = new ForeignKeyComparator();

        $a = new ForeignKey('fk_posts_user', ['user_id'], 'users', ['id'], ReferentialAction::NoAction, 'NO ACTION');
        $b = new ForeignKey('fk_posts_user', ['user_id'], 'users', ['id'], null, null);

        self::assertTrue($comparator->areEqual($a, $b));
    }

    public function testDetectsDifferentForeignKeys(): void
    {
        $comparator = new ForeignKeyComparator();

        $a = new ForeignKey('fk_posts_user', ['user_id'], 'users', ['id']);
        $b = new ForeignKey('fk_posts_author', ['author_id'], 'users', ['id']);

        self::assertFalse($comparator->areEqual($a, $b));
    }

    public function testKeepsUnknownReferentialActionStringsComparable(): void
    {
        $comparator = new ForeignKeyComparator();

        $a = new ForeignKey('fk_posts_user', ['user_id'], 'users', ['id'], 'CUSTOM ACTION', null);
        $b = new ForeignKey('fk_posts_user', ['user_id'], 'users', ['id'], 'CUSTOM ACTION', null);

        self::assertTrue($comparator->areEqual($a, $b));
    }
}
