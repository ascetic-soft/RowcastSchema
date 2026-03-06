<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

use AsceticSoft\RowcastSchema\Cli\Command\CommandInterface;
use AsceticSoft\RowcastSchema\Cli\Command\DiffCommand;
use AsceticSoft\RowcastSchema\Cli\Command\MigrateCommand;
use AsceticSoft\RowcastSchema\Cli\Command\RollbackCommand;
use AsceticSoft\RowcastSchema\Cli\Command\StatusCommand;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Migration\DatabaseMigrationRepository;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use AsceticSoft\RowcastSchema\Parser\PhpSchemaParser;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;
use AsceticSoft\RowcastSchema\Parser\YamlSchemaParser;
use AsceticSoft\RowcastSchema\Platform\PlatformFactory;

final class Application
{
    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? null;
        if (!\is_string($commandName) || $commandName === '') {
            $this->printUsage();
            return 1;
        }

        $configFile = getcwd() . '/rowcast-schema.php';

        try {
            $config = Config::fromFile($configFile);

            $parser = $this->createParser($config->schemaPath);
            $differ = new SchemaDiffer();
            $introspectorFactory = new IntrospectorFactory();
            $platform = new PlatformFactory()->createForPdo($config->pdo);
            $loader = new MigrationLoader();
            $repository = new DatabaseMigrationRepository($config->pdo, $config->migrationTableName);
            $runner = new MigrationRunner($config->pdo, $loader, $repository, $platform);
            $tableIgnoreMatcher = new TableIgnoreMatcher($config->ignoreTableRules, $config->migrationTableName);

            $commands = [
                'diff' => new DiffCommand($parser, $introspectorFactory, $differ, new MigrationGenerator(), $tableIgnoreMatcher),
                'migrate' => new MigrateCommand($runner),
                'rollback' => new RollbackCommand($runner),
                'status' => new StatusCommand($parser, $introspectorFactory, $differ, $loader, $repository, $tableIgnoreMatcher),
            ];

            $command = $commands[$commandName] ?? null;
            if (!$command instanceof CommandInterface) {
                $this->printUsage();
                return 1;
            }

            return $command->execute(\array_slice($argv, 2), $config);
        } catch (\Throwable $e) {
            fwrite(STDERR, '[rowcast-schema] ' . $e->getMessage() . PHP_EOL);
            return 1;
        }
    }

    private function printUsage(): void
    {
        echo <<<TXT
            Rowcast Schema CLI

            Usage:
              rowcast-schema diff [--dry-run]
              rowcast-schema migrate
              rowcast-schema rollback [--step=N]
              rowcast-schema status

            TXT;
    }

    private function createParser(string $schemaPath): SchemaParserInterface
    {
        $extension = strtolower(pathinfo($schemaPath, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => new PhpSchemaParser(),
            'yaml', 'yml' => new YamlSchemaParser(),
            default => throw new \InvalidArgumentException(\sprintf(
                'Unsupported schema file extension "%s". Use .php, .yaml, or .yml.',
                $extension !== '' ? $extension : '(none)',
            )),
        };
    }
}
