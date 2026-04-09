<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

use AsceticSoft\RowcastSchema\Schema\Index;

final readonly class AddIndex implements OperationInterface
{
    public function __construct(
        public string $tableName,
        public Index $index,
    ) {
    }

    public function reverse(): DropIndex
    {
        return new DropIndex($this->tableName, $this->index->name);
    }
}
