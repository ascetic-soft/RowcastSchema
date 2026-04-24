<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

final class ConfigNormalizer
{
    /**
     * @param array<mixed, mixed> $config
     * @return array{schemaPath: string, migrationsPath: string, migrationTableName: string, connection: array<mixed, mixed>, ignoreTableRules: list<string|\Closure(string): bool>}
     */
    public function normalize(array $config, string $cwd): array
    {
        $connection = $config['connection'] ?? null;
        if (!\is_array($connection)) {
            throw new \RuntimeException('Config must contain "connection" mapping.');
        }

        $migrationTableNameRaw = $config['migration_table'] ?? '_rowcast_migrations';
        if (!\is_string($migrationTableNameRaw) || $migrationTableNameRaw === '') {
            throw new \RuntimeException('Config "migration_table" must be a non-empty string.');
        }

        $ignoreConfig = $config['ignore_tables'] ?? [];
        if (!\is_array($ignoreConfig)) {
            throw new \RuntimeException('Config "ignore_tables" must be an array.');
        }

        return [
            'schemaPath' => isset($config['schema']) && \is_string($config['schema']) ? $config['schema'] : $cwd . '/schema.php',
            'migrationsPath' => isset($config['migrations']) && \is_string($config['migrations'])
                ? $config['migrations']
                : $cwd . '/migrations',
            'migrationTableName' => $migrationTableNameRaw,
            'connection' => $connection,
            'ignoreTableRules' => $this->normalizeIgnoreTableRules($ignoreConfig),
        ];
    }

    /**
     * @param array<mixed, mixed> $ignoreConfig
     * @return list<string|\Closure(string): bool>
     */
    private function normalizeIgnoreTableRules(array $ignoreConfig): array
    {
        $rules = [];
        foreach ($ignoreConfig as $rule) {
            if (\is_string($rule)) {
                if ($rule === '') {
                    throw new \RuntimeException('Ignore table regex rule must be a non-empty string.');
                }
                set_error_handler(static fn (): bool => true);
                $isValidPattern = preg_match($rule, '') !== false;
                restore_error_handler();
                if (!$isValidPattern) {
                    throw new \RuntimeException(\sprintf('Invalid ignore table regex pattern: %s', $rule));
                }
                $rules[] = $rule;
                continue;
            }

            if (\is_callable($rule)) {
                $rules[] = $rule(...);
                continue;
            }

            throw new \RuntimeException('Each "ignore_tables" rule must be a regex string or callable.');
        }

        return $rules;
    }
}
