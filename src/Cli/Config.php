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
            throw new \RuntimeException(sprintf('Config file not found: %s', $path));
        }

        /** @var mixed $config */
        $config = require $path;
        if (!is_array($config)) {
            throw new \RuntimeException('Config file must return array.');
        }

        $connection = $config['connection'] ?? null;
        if (!is_array($connection)) {
            throw new \RuntimeException('Config must contain "connection" mapping.');
        }

        $dsn = (string)($connection['dsn'] ?? '');
        if ($dsn === '') {
            throw new \RuntimeException('Connection "dsn" is required.');
        }

        $username = isset($connection['username']) ? (string)$connection['username'] : null;
        $password = isset($connection['password']) ? (string)$connection['password'] : null;
        $options = $connection['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }

        $pdo = new \PDO($dsn, $username, $password, $options);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return new self(
            schemaPath: (string)($config['schema'] ?? getcwd() . '/schema.php'),
            migrationsPath: (string)($config['migrations'] ?? getcwd() . '/migrations'),
            pdo: $pdo,
        );
    }
}
