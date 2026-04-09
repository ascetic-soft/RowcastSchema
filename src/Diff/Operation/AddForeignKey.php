<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

use AsceticSoft\RowcastSchema\Schema\ForeignKey;

final readonly class AddForeignKey implements OperationInterface
{
    public function __construct(
        public string $tableName,
        public ForeignKey $foreignKey,
    ) {
    }

    public function reverse(): DropForeignKey
    {
        return new DropForeignKey($this->tableName, $this->foreignKey->name, $this->foreignKey);
    }
}
