<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

use AsceticSoft\RowcastSchema\Cli\Command\CommandInterface;
use AsceticSoft\RowcastSchema\Cli\Command\DiffCommand;
use AsceticSoft\RowcastSchema\Cli\Command\MakeCommand;
use AsceticSoft\RowcastSchema\Cli\Command\MigrateCommand;
use AsceticSoft\RowcastSchema\Cli\Command\RollbackCommand;
use AsceticSoft\RowcastSchema\Cli\Command\StatusCommand;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Migration\DatabaseMigrationRepository;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use AsceticSoft\RowcastSchema\Platform\PlatformFactory;
use AsceticSoft\RowcastSchema\Parser\SchemaParserFactory;

final readonly class ApplicationContainer
{
    /**
     * @return array<string, CommandInterface>
     */
    public function buildCommands(Config $config, ConsoleOutput $output): array
    {
        $parser = new SchemaParserFactory()->create($config->schemaPath);
        $differ = new SchemaDiffer();
        $introspector = new IntrospectorFactory()->createForPdo($config->pdo);
        $platform = new PlatformFactory()->createForPdo($config->pdo);
        $loader = new MigrationLoader();
        $repository = new DatabaseMigrationRepository($config->pdo, $config->migrationTableName);
        $runner = new MigrationRunner($config->pdo, $loader, $repository, $platform);
        $tableIgnoreMatcher = new TableIgnoreMatcher($config->ignoreTableRules, $config->migrationTableName);
        $operationDescriber = new OperationDescriber();
        $generator = new MigrationGenerator();
        $schemaDiffService = new SchemaDiffService($parser, $introspector, $differ, $tableIgnoreMatcher);

        return [
            'diff' => new DiffCommand(
                $schemaDiffService,
                $generator,
                $output,
                $operationDescriber,
            ),
            'make' => new MakeCommand($generator, $output),
            'migrate' => new MigrateCommand($runner, $output),
            'rollback' => new RollbackCommand($runner, $output),
            'status' => new StatusCommand(
                $schemaDiffService,
                $loader,
                $repository,
                $output,
                $operationDescriber,
            ),
        ];
    }
}
