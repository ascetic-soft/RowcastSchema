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
use AsceticSoft\RowcastSchema\Parser\AttributeSchemaParser;
use AsceticSoft\RowcastSchema\Parser\PhpSchemaParser;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;
use AsceticSoft\RowcastSchema\Parser\YamlSchemaParser;
use AsceticSoft\RowcastSchema\Platform\PlatformFactory;

final readonly class ApplicationContainer
{
    /**
     * @return array<string, CommandInterface>
     */
    public function buildCommands(Config $config, ConsoleOutput $output): array
    {
        $parser = $this->createParser($config->schemaPath);
        $differ = new SchemaDiffer();
        $introspector = new IntrospectorFactory()->createForPdo($config->pdo);
        $platform = new PlatformFactory()->createForPdo($config->pdo);
        $loader = new MigrationLoader();
        $repository = new DatabaseMigrationRepository($config->pdo, $config->migrationTableName);
        $runner = new MigrationRunner($config->pdo, $loader, $repository, $platform);
        $tableIgnoreMatcher = new TableIgnoreMatcher($config->ignoreTableRules, $config->migrationTableName);
        $operationDescriber = new OperationDescriber();
        $generator = new MigrationGenerator();

        return [
            'diff' => new DiffCommand(
                $parser,
                $introspector,
                $differ,
                $generator,
                $tableIgnoreMatcher,
                $output,
                $operationDescriber,
            ),
            'make' => new MakeCommand($generator, $output),
            'migrate' => new MigrateCommand($runner, $output),
            'rollback' => new RollbackCommand($runner, $output),
            'status' => new StatusCommand(
                $parser,
                $introspector,
                $differ,
                $loader,
                $repository,
                $tableIgnoreMatcher,
                $output,
                $operationDescriber,
            ),
        ];
    }

    private function createParser(string $schemaPath): SchemaParserInterface
    {
        if (is_dir($schemaPath)) {
            return new AttributeSchemaParser();
        }

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
