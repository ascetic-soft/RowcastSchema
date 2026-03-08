<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\ConsoleOutput;
use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;

final readonly class MigrateCommand implements CommandInterface
{
    public function __construct(
        private MigrationRunner $runner,
        private ConsoleOutput $output,
    )
    {
    }

    public function execute(array $args, Config $config): int
    {
        $this->output->title('migrate');
        $this->output->newLine();

        $appliedVersions = [];
        $count = $this->runner->migrate(
            $config->migrationsPath,
            static function (string $version) use (&$appliedVersions): void {
                $appliedVersions[] = $version;
            },
        );

        if ($count === 0) {
            $this->output->success('Nothing to migrate.');
            return 0;
        }

        $this->output->info('Applying migrations...');
        $this->output->newLine();
        foreach ($appliedVersions as $version) {
            $this->output->line('[OK] ' . $version, 4);
        }

        $this->output->newLine();
        $this->output->success(\sprintf(
            'Applied %d %s.',
            $count,
            $count === 1 ? 'migration' : 'migrations',
        ));

        return 0;
    }
}
