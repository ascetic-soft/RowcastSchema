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
        [$globalOptions, $commandArgv] = $this->extractGlobalOptions($argv);
        $output = new ConsoleOutput((bool)($globalOptions['no-ansi'] ?? false));

        $commandName = $commandArgv[1] ?? null;
        if (!\is_string($commandName) || $commandName === '') {
            $this->printUsage($output);
            return 1;
        }

        $configFile = $globalOptions['config'] ?? (getcwd() . '/rowcast-schema.php');

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
            $operationDescriber = new OperationDescriber();

            $commands = [
                'diff' => new DiffCommand(
                    $parser,
                    $introspectorFactory,
                    $differ,
                    new MigrationGenerator(),
                    $tableIgnoreMatcher,
                    $output,
                    $operationDescriber,
                ),
                'make' => new MakeCommand(new MigrationGenerator(), $output),
                'migrate' => new MigrateCommand($runner, $output),
                'rollback' => new RollbackCommand($runner, $output),
                'status' => new StatusCommand(
                    $parser,
                    $introspectorFactory,
                    $differ,
                    $loader,
                    $repository,
                    $tableIgnoreMatcher,
                    $output,
                    $operationDescriber,
                ),
            ];

            $command = $commands[$commandName] ?? null;
            if (!$command instanceof CommandInterface) {
                $this->printUsage($output);
                return 1;
            }

            return $command->execute(\array_slice($commandArgv, 2), $config);
        } catch (\Throwable $e) {
            $output->error($e->getMessage());
            return 1;
        }
    }

    private function printUsage(ConsoleOutput $output): void
    {
        $output->title('cli');
        $output->newLine();
        $output->line('Usage:', 2);
        $output->line('rowcast-schema [--config=path] [--no-ansi] diff [--dry-run]', 4);
        $output->line('rowcast-schema [--config=path] [--no-ansi] make', 4);
        $output->line('rowcast-schema [--config=path] [--no-ansi] migrate', 4);
        $output->line('rowcast-schema [--config=path] [--no-ansi] rollback [--step=N]', 4);
        $output->line('rowcast-schema [--config=path] [--no-ansi] status', 4);
    }

    /**
     * @param list<string> $argv
     *
     * @return array{0: array{config?: string, no-ansi?: bool}, 1: list<string>}
     */
    private function extractGlobalOptions(array $argv): array
    {
        $configPath = null;
        $noAnsi = false;
        $filtered = [];
        $count = \count($argv);

        for ($index = 0; $index < $count; $index++) {
            $arg = $argv[$index];

            if (\str_starts_with($arg, '--config=')) {
                $value = \trim(\substr($arg, 9));
                if ($value === '') {
                    throw new \RuntimeException('Option "--config" requires a non-empty path.');
                }
                $configPath = $value;
                continue;
            }

            if ($arg === '--config') {
                $value = $argv[$index + 1] ?? null;
                if (!\is_string($value) || $value === '') {
                    throw new \RuntimeException('Option "--config" requires a non-empty path.');
                }
                $configPath = $value;
                $index++;
                continue;
            }

            if ($arg === '--no-ansi') {
                $noAnsi = true;
                continue;
            }

            $filtered[] = $arg;
        }

        $options = [];
        if ($configPath !== null) {
            $options['config'] = $configPath;
        }
        if ($noAnsi) {
            $options['no-ansi'] = true;
        }

        return [$options, $filtered];
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
