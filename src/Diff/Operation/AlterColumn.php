<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

use AsceticSoft\RowcastSchema\Schema\Column;

final readonly class AlterColumn implements OperationInterface
{
    public function __construct(
        public string $tableName,
        public string $columnName,
        public Column $newColumn,
        public ?Column $oldColumn = null,
    ) {
    }
}
