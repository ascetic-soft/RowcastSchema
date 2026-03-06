<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;

final readonly class DiffCommand implements CommandInterface
{
    public function __construct(
        private SchemaParserInterface $parser,
        private IntrospectorFactory $introspectorFactory,
        private SchemaDiffer $differ,
        private MigrationGenerator $generator,
    ) {
    }

    public function execute(array $args, Config $config): int
    {
        $target = $this->parser->parse($config->schemaPath);
        $introspector = $this->introspectorFactory->createForPdo($config->pdo);
        $current = $introspector->introspect($config->pdo);
        $operations = $this->differ->diff($current, $target);

        if (in_array('--dry-run', $args, true)) {
            if ($operations === []) {
                echo "No schema changes detected.\n";
                return 0;
            }
            foreach ($operations as $op) {
                echo $op::class . PHP_EOL;
            }
            return 0;
        }

        if ($operations === []) {
            echo "No schema changes detected. Migration file was not created.\n";
            return 0;
        }

        $file = $this->generator->generate($operations, $config->migrationsPath);
        echo sprintf("Migration generated: %s\n", $file);
        return 0;
    }
}
