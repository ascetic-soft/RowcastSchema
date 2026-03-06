<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;

final readonly class RollbackCommand implements CommandInterface
{
    public function __construct(private MigrationRunner $runner)
    {
    }

    public function execute(array $args, Config $config): int
    {
        $step = 1;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--step=')) {
                $step = max(1, (int)substr($arg, 7));
            }
        }

        $count = $this->runner->rollback($config->migrationsPath, $step);
        echo \sprintf("Rolled back migrations: %d\n", $count);
        return 0;
    }
}
