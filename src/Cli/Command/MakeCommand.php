<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;

final readonly class MakeCommand implements CommandInterface
{
    public function __construct(private MigrationGenerator $generator)
    {
    }

    public function execute(array $args, Config $config): int
    {
        $file = $this->generator->generate([], $config->migrationsPath);
        echo \sprintf("Empty migration generated: %s\n", $file);

        return 0;
    }
}
