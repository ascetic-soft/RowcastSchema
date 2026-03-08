<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\ConsoleOutput;
use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;

final readonly class MakeCommand implements CommandInterface
{
    public function __construct(
        private MigrationGenerator $generator,
        private ConsoleOutput $output,
    )
    {
    }

    public function execute(array $args, Config $config): int
    {
        $this->output->title('make');
        $this->output->newLine();

        $file = $this->generator->generate([], $config->migrationsPath);
        $this->output->success(\sprintf('Empty migration generated: %s', pathinfo($file, PATHINFO_FILENAME)));
        $this->output->line($file, 4);

        return 0;
    }
}
