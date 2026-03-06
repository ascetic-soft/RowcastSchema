<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

final readonly class DropColumn implements OperationInterface
{
    public function __construct(
        public string $tableName,
        public string $columnName,
    ) {
    }
}
