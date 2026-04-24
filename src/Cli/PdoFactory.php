<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

final class PdoFactory
{
    /**
     * @param array<mixed, mixed> $connection
     */
    public function create(array $connection): \PDO
    {
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

        return $pdo;
    }
}
