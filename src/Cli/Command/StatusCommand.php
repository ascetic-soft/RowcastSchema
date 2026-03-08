<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\ConsoleOutput;
use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Cli\OperationDescriber;
use AsceticSoft\RowcastSchema\Cli\TableIgnoreMatcher;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRepositoryInterface;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;

final readonly class StatusCommand implements CommandInterface
{
    public function __construct(
        private SchemaParserInterface $parser,
        private IntrospectorFactory $introspectorFactory,
        private SchemaDiffer $differ,
        private MigrationLoader $loader,
        private MigrationRepositoryInterface $repository,
        private TableIgnoreMatcher $tableIgnoreMatcher,
        private ConsoleOutput $output,
        private OperationDescriber $operationDescriber,
    ) {
    }

    public function execute(array $args, Config $config): int
    {
        $this->output->title('status');
        $this->output->newLine();

        $this->repository->ensureTable();

        $all = array_keys($this->loader->load($config->migrationsPath));
        $applied = $this->repository->getApplied();
        $appliedMap = array_flip($applied);
        $pending = array_values(array_filter($all, static fn (string $v): bool => !isset($appliedMap[$v])));

        $this->output->line('Migrations:', 2);
        $this->output->newLine();
        if ($all === []) {
            $this->output->line('(no migration files found)', 4);
        } else {
            foreach ($all as $version) {
                if (isset($appliedMap[$version])) {
                    $this->output->line(\sprintf('[OK] %s  applied', $version), 4);
                    continue;
                }

                $this->output->line(\sprintf('[..] %s  pending', $version), 4);
            }
        }

        $this->output->newLine();
        $this->output->info(\sprintf('Applied: %d | Pending: %d', \count($applied), \count($pending)));
        $this->output->newLine();

        $target = $this->tableIgnoreMatcher->filterSchema($this->parser->parse($config->schemaPath));
        $current = $this->tableIgnoreMatcher->filterSchema(
            $this->introspectorFactory->createForPdo($config->pdo)->introspect($config->pdo),
        );
        $diff = $this->differ->diff($current, $target);

        if ($diff === []) {
            $this->output->success('Schema: in sync.');
        } else {
            $this->output->warning(\sprintf(
                'Schema: %d %s detected.',
                \count($diff),
                \count($diff) === 1 ? 'operation' : 'operations',
            ));
            $this->output->newLine();
            foreach ($diff as $operation) {
                $this->output->line($this->operationDescriber->describe($operation), 4);
            }
            $this->output->newLine();
            $this->output->info('Summary: ' . $this->operationDescriber->describeSummary($diff));
        }

        return 0;
    }
}
