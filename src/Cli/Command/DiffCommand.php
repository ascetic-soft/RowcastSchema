<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\ConsoleOutput;
use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Cli\OperationDescriber;
use AsceticSoft\RowcastSchema\Cli\SchemaDiffService;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;

final readonly class DiffCommand implements CommandInterface
{
    public function __construct(
        private SchemaDiffService $schemaDiffService,
        private MigrationGenerator $generator,
        private ConsoleOutput $output,
        private OperationDescriber $operationDescriber,
    ) {
    }

    public function execute(array $args, Config $config): int
    {
        $isDryRun = \in_array('--dry-run', $args, true);

        $this->output->title($isDryRun ? 'diff (dry-run)' : 'diff');
        $this->output->newLine();

        $operations = $this->schemaDiffService->diff($config);

        if ($operations === []) {
            if ($isDryRun) {
                $this->output->success('No schema changes detected.');
            } else {
                $this->output->success('No schema changes detected. Migration file was not created.');
            }

            return 0;
        }

        $this->output->info(\sprintf(
            'Detected %d %s:',
            \count($operations),
            \count($operations) === 1 ? 'operation' : 'operations',
        ));
        $this->output->newLine();

        foreach ($operations as $operation) {
            $this->output->line($this->operationDescriber->describe($operation), 4);
            if ($isDryRun) {
                foreach ($this->operationDescriber->describeDetails($operation) as $detailLine) {
                    $this->output->line($detailLine, 8);
                }
            }
        }

        $this->output->newLine();
        $this->output->info('Summary: ' . $this->operationDescriber->describeSummary($operations));

        if ($isDryRun) {
            return 0;
        }

        $file = $this->generator->generate($operations, $config->migrationsPath);
        $this->output->newLine();
        $this->output->success(\sprintf('Migration generated: %s', pathinfo($file, PATHINFO_FILENAME)));
        $this->output->line($file, 4);

        return 0;
    }
}
