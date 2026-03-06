<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

final readonly class DatabaseMigrationRepository implements MigrationRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private string $tableName = '_rowcast_migrations',
    ) {
    }

    public function ensureTable(): void
    {
        $driverRaw = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (!\is_string($driverRaw) || $driverRaw === '') {
            throw new \RuntimeException('Unable to detect PDO driver name.');
        }
        $driver = $driverRaw;
        $versionType = 'VARCHAR(255)';
        $datetimeType = match ($driver) {
            'pgsql' => 'TIMESTAMP',
            'sqlite' => 'TEXT',
            default => 'DATETIME',
        };

        $sql = \sprintf(
            'CREATE TABLE IF NOT EXISTS %s (version %s PRIMARY KEY, applied_at %s NOT NULL)',
            $this->tableName,
            $versionType,
            $datetimeType,
        );

        $this->pdo->exec($sql);
    }

    public function getApplied(): array
    {
        $stmt = $this->pdo->query(\sprintf('SELECT version FROM %s ORDER BY version', $this->tableName));
        if ($stmt === false) {
            return [];
        }

        $versions = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $value) {
            if (\is_string($value)) {
                $versions[] = $value;
            }
        }

        return $versions;
    }

    public function markApplied(string $version): void
    {
        $stmt = $this->pdo->prepare(\sprintf(
            'INSERT INTO %s (version, applied_at) VALUES (:version, :applied_at)',
            $this->tableName,
        ));
        $stmt->execute([
            'version' => $version,
            'applied_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markRolledBack(string $version): void
    {
        $stmt = $this->pdo->prepare(\sprintf('DELETE FROM %s WHERE version = :version', $this->tableName));
        $stmt->execute(['version' => $version]);
    }
}
