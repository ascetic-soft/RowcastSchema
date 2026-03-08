<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

use AsceticSoft\RowcastSchema\Platform\PlatformInterface;
use AsceticSoft\RowcastSchema\Platform\SqlitePlatform;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

final readonly class MigrationRunner
{
    public function __construct(
        private \PDO $pdo,
        private MigrationLoader $loader,
        private MigrationRepositoryInterface $repository,
        private PlatformInterface $platform,
    ) {
    }

    public function migrate(string $migrationsPath, ?\Closure $onVersion = null): int
    {
        $this->repository->ensureTable();

        $all = $this->loader->load($migrationsPath);
        $applied = array_flip($this->repository->getApplied());
        $count = 0;

        foreach ($all as $version => $filePath) {
            if (isset($applied[$version])) {
                continue;
            }

            $this->runUpMigration($version, $filePath);
            $this->repository->markApplied($version);
            $onVersion?->__invoke($version);
            $count++;
        }

        return $count;
    }

    public function rollback(string $migrationsPath, int $step = 1, ?\Closure $onVersion = null): int
    {
        $this->repository->ensureTable();

        $all = $this->loader->load($migrationsPath);
        $applied = $this->repository->getApplied();
        rsort($applied, SORT_STRING);

        $count = 0;
        foreach (\array_slice($applied, 0, max(0, $step)) as $version) {
            $filePath = $all[$version] ?? null;
            if ($filePath === null) {
                continue;
            }

            $this->runDownMigration($version, $filePath);
            $this->repository->markRolledBack($version);
            $onVersion?->__invoke($version);
            $count++;
        }

        return $count;
    }

    private function runUpMigration(string $version, string $filePath): void
    {
        $migration = $this->instantiateMigration($version, $filePath);
        $builder = new SchemaBuilder();
        $migration->up($builder);
        $this->executeOperations($builder);
    }

    private function runDownMigration(string $version, string $filePath): void
    {
        $migration = $this->instantiateMigration($version, $filePath);
        $builder = new SchemaBuilder();
        $migration->down($builder);
        $this->executeOperations($builder);
    }

    private function instantiateMigration(string $version, string $filePath): MigrationInterface
    {
        if (!class_exists($version, false)) {
            require_once $filePath;
        }

        if (!class_exists($version)) {
            throw new \RuntimeException(\sprintf('Migration class "%s" was not found in %s.', $version, $filePath));
        }

        $migration = new $version();
        if (!$migration instanceof MigrationInterface) {
            throw new \RuntimeException(\sprintf('Migration "%s" must implement MigrationInterface.', $version));
        }

        return $migration;
    }

    private function executeOperations(SchemaBuilder $builder): void
    {
        $executor = function () use ($builder): void {
            $sqliteRebuilder = $this->platform instanceof SqlitePlatform ? new SqliteTableRebuilder() : null;
            foreach ($builder->getOperations() as $operation) {
                if ($sqliteRebuilder !== null && $sqliteRebuilder->supports($operation)) {
                    $sqliteRebuilder->execute($this->pdo, $operation);
                    continue;
                }
                foreach ($this->platform->toSql($operation) as $sql) {
                    $this->pdo->exec($sql);
                }
            }
        };

        if ($this->platform->supportsDdlTransactions()) {
            $this->pdo->beginTransaction();
            try {
                $executor();
                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
            return;
        }

        $executor();
    }
}
