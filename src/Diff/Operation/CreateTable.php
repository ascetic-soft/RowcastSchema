<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

use AsceticSoft\RowcastSchema\Schema\Table;

final readonly class CreateTable implements OperationInterface
{
    public function __construct(public Table $table)
    {
    }

    public function reverse(): DropTable
    {
        return new DropTable($this->table->name);
    }
}
