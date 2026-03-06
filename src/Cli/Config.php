<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

final readonly class Config
{
    public function __construct(
        public string $schemaPath,
        public string $migrationsPath,
        public \PDO $pdo,
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

        return new self(
            schemaPath: isset($config['schema']) && \is_string($config['schema']) ? $config['schema'] : getcwd() . '/schema.php',
            migrationsPath: isset($config['migrations']) && \is_string($config['migrations'])
                ? $config['migrations']
                : getcwd() . '/migrations',
            pdo: $pdo,
        );
    }
}
