<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Config;
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
    ) {
    }

    public function execute(array $args, Config $config): int
    {
        $this->repository->ensureTable();

        $all = array_keys($this->loader->load($config->migrationsPath));
        $applied = $this->repository->getApplied();
        $appliedMap = array_flip($applied);
        $pending = array_values(array_filter($all, static fn (string $v): bool => !isset($appliedMap[$v])));

        echo sprintf("Applied: %d\n", count($applied));
        echo sprintf("Pending: %d\n", count($pending));

        if ($pending !== []) {
            echo "Pending migrations:\n";
            foreach ($pending as $version) {
                echo " - {$version}\n";
            }
        }

        $target = $this->parser->parse($config->schemaPath);
        $current = $this->introspectorFactory->createForPdo($config->pdo)->introspect($config->pdo);
        $diff = $this->differ->diff($current, $target);

        if ($diff === []) {
            echo "Schema is in sync.\n";
        } else {
            echo sprintf("Schema diff operations: %d\n", count($diff));
        }

        return 0;
    }
}
