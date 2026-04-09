<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff\Operation;

final readonly class RawSql implements OperationInterface
{
    public function __construct(public string $sql)
    {
    }

    public function reverse(): ?OperationInterface
    {
        return null;
    }
}
