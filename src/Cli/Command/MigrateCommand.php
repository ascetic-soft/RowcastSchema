<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;

final readonly class MigrateCommand implements CommandInterface
{
    public function __construct(private MigrationRunner $runner)
    {
    }

    public function execute(array $args, Config $config): int
    {
        $count = $this->runner->migrate($config->migrationsPath);
        echo sprintf("Applied migrations: %d\n", $count);
        return 0;
    }
}
