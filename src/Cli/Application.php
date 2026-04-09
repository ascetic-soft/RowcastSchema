<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

use AsceticSoft\RowcastSchema\Cli\Command\CommandInterface;

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
            $commands = new ApplicationContainer()->buildCommands($config, $output);

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

        foreach ($argv as $index => $indexValue) {
            $arg = $indexValue;

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

}
