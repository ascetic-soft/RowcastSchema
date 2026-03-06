<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

interface MigrationInterface
{
    public function up(SchemaBuilder $schema): void;

    public function down(SchemaBuilder $schema): void;
}
