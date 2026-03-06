<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

use AsceticSoft\RowcastSchema\Schema\ForeignKey;

final readonly class DropForeignKey implements OperationInterface
{
    public function __construct(
        public string $tableName,
        public string $foreignKeyName,
        public ?ForeignKey $foreignKey = null,
    ) {
    }
}
