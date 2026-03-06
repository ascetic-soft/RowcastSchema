<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;

interface PlatformInterface
{
    /**
     * @return list<string>
     */
    public function toSql(OperationInterface $operation): array;

    public function supportsDdlTransactions(): bool;
}
