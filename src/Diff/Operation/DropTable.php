<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

final readonly class DropTable implements OperationInterface
{
    public function __construct(public string $tableName)
    {
    }

    public function reverse(): ?OperationInterface
    {
        return null;
    }
}
