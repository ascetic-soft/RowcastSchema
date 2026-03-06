<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Config;

interface CommandInterface
{
    /**
     * @param list<string> $args
     */
    public function execute(array $args, Config $config): int;
}
