<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Migration;

use AsceticSoft\RowcastSchema\Migration\DatabaseMigrationRepository;
use PHPUnit\Framework\TestCase;

final class DatabaseMigrationRepositoryTest extends TestCase
{
    public function testEnsureTableMarkAppliedAndRollback(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $repo = new DatabaseMigrationRepository($pdo, '_test_migrations');

        $repo->ensureTable();
        self::assertSame([], $repo->getApplied());

        $repo->markApplied('Migration_20260101_000001');
        $repo->markApplied('Migration_20260101_000002');
        self::assertSame(['Migration_20260101_000001', 'Migration_20260101_000002'], $repo->getApplied());

        $repo->markRolledBack('Migration_20260101_000001');
        self::assertSame(['Migration_20260101_000002'], $repo->getApplied());
    }
}
