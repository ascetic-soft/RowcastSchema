<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

final readonly class Config
{
    /**
     * @param list<string|\Closure(string):bool> $ignoreTableRules
     */
    public function __construct(
        public string $schemaPath,
        public string $migrationsPath,
        public string $migrationTableName,
        public \PDO $pdo,
        public array $ignoreTableRules = [],
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Config file not found: %s', $path));
        }

        $config = require $path;
        if (!\is_array($config)) {
            throw new \RuntimeException('Config file must return array.');
        }

        $connection = $config['connection'] ?? null;
        if (!\is_array($connection)) {
            throw new \RuntimeException('Config must contain "connection" mapping.');
        }

        $dsnRaw = $connection['dsn'] ?? null;
        $dsn = \is_string($dsnRaw) ? $dsnRaw : '';
        if ($dsn === '') {
            throw new \RuntimeException('Connection "dsn" is required.');
        }

        $username = isset($connection['username']) && \is_string($connection['username']) ? $connection['username'] : null;
        $password = isset($connection['password']) && \is_string($connection['password']) ? $connection['password'] : null;
        $options = $connection['options'] ?? [];
        if (!\is_array($options)) {
            $options = [];
        }

        $pdo = new \PDO($dsn, $username, $password, $options);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $migrationTableNameRaw = $config['migration_table'] ?? '_rowcast_migrations';
        if (!\is_string($migrationTableNameRaw) || $migrationTableNameRaw === '') {
            throw new \RuntimeException('Config "migration_table" must be a non-empty string.');
        }

        $ignoreConfig = $config['ignore_tables'] ?? [];
        if (!\is_array($ignoreConfig)) {
            throw new \RuntimeException('Config "ignore_tables" must be an array.');
        }

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
                $rules[] = \Closure::fromCallable($rule);
                continue;
            }

            throw new \RuntimeException('Each "ignore_tables" rule must be a regex string or callable.');
        }

        return new self(
            schemaPath: isset($config['schema']) && \is_string($config['schema']) ? $config['schema'] : getcwd() . '/schema.php',
            migrationsPath: isset($config['migrations']) && \is_string($config['migrations'])
                ? $config['migrations']
                : getcwd() . '/migrations',
            migrationTableName: $migrationTableNameRaw,
            pdo: $pdo,
            ignoreTableRules: $rules,
        );
    }
}
