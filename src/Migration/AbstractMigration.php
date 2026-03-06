<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

abstract class AbstractMigration implements MigrationInterface
{
    public function down(SchemaBuilder $schema): void
    {
    }
}
