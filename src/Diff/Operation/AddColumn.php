<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

use AsceticSoft\RowcastSchema\Schema\Column;

final readonly class AddColumn implements OperationInterface
{
    public function __construct(
        public string $tableName,
        public Column $column,
    ) {
    }
}
