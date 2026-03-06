<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

use AsceticSoft\RowcastSchema\Schema\Column;

final readonly class AlterColumn implements OperationInterface
{
    public function __construct(
        public string $tableName,
        public Column $oldColumn,
        public Column $newColumn,
    ) {
    }
}
