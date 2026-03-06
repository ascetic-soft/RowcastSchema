<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Platform;

final readonly class MysqlPlatform extends AbstractPlatform
{
    public function supportsDdlTransactions(): bool
    {
        return false;
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return \sprintf('`%s`', str_replace('`', '``', $identifier));
    }
}
