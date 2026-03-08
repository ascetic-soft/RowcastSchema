<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\ConsoleOutput;
use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;

final readonly class RollbackCommand implements CommandInterface
{
    public function __construct(
        private MigrationRunner $runner,
        private ConsoleOutput $output,
    )
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

        $this->output->title(\sprintf('rollback (step: %d)', $step));
        $this->output->newLine();

        $rolledBackVersions = [];
        $count = $this->runner->rollback(
            $config->migrationsPath,
            $step,
            static function (string $version) use (&$rolledBackVersions): void {
                $rolledBackVersions[] = $version;
            },
        );

        if ($count === 0) {
            $this->output->success('Nothing to rollback.');
            return 0;
        }

        $this->output->info('Rolling back migrations...');
        $this->output->newLine();
        foreach ($rolledBackVersions as $version) {
            $this->output->line('[OK] ' . $version, 4);
        }

        $this->output->newLine();
        $this->output->success(\sprintf(
            'Rolled back %d %s.',
            $count,
            $count === 1 ? 'migration' : 'migrations',
        ));

        return 0;
    }
}
