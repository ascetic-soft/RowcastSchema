<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Migration;

use AsceticSoft\RowcastSchema\Migration\AbstractMigration;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;
use PHPUnit\Framework\TestCase;

final class AbstractMigrationTest extends TestCase
{
    public function testDefaultDownMethodDoesNothing(): void
    {
        $migration = new class () extends AbstractMigration {
            public function up(SchemaBuilder $schema): void
            {
            }
        };

        $builder = new SchemaBuilder();
        $migration->down($builder);

        self::assertSame([], $builder->getOperations());
    }
}
