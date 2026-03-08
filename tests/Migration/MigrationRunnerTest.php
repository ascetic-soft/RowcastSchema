<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Migration;

use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRepositoryInterface;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use AsceticSoft\RowcastSchema\Platform\PlatformInterface;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    public function testMigrateSkipsAlreadyAppliedAndMarksOnlyNewOnes(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $dir = sys_get_temp_dir() . '/rowcast_runner_' . uniqid('', true);
        mkdir($dir, 0o777, true);

        $oldClass = 'Migration_' . str_replace('.', '', uniqid('20260101_000001_', true));
        $newClass = 'Migration_' . str_replace('.', '', uniqid('20260101_000002_', true));
        $oldFile = $this->writeMigrationFile($dir, $oldClass, '$schema->createTable(\'t_old\', static function ($t): void { $t->column(\'id\', \'integer\')->primaryKey(); });');
        $newFile = $this->writeMigrationFile($dir, $newClass, '$schema->createTable(\'t_new\', static function ($t): void { $t->column(\'id\', \'integer\')->primaryKey(); });');

        $repo = new class ([$oldClass]) implements MigrationRepositoryInterface {
            /** @var list<string> */
            public array $applied;
            /** @var list<string> */
            public array $marked = [];
            public bool $ensured = false;

            /** @param list<string> $applied */
            public function __construct(array $applied)
            {
                $this->applied = $applied;
            }
            public function ensureTable(): void
            {
                $this->ensured = true;
            }
            public function getApplied(): array
            {
                return $this->applied;
            }
            public function markApplied(string $version): void
            {
                $this->marked[] = $version;
                $this->applied[] = $version;
            }
            public function markRolledBack(string $version): void
            {
            }
        };

        $runner = new MigrationRunner($pdo, new MigrationLoader(), $repo, $this->sqlitePlatform());
        $count = $runner->migrate($dir);

        self::assertTrue($repo->ensured);
        self::assertSame(1, $count);
        self::assertSame([$newClass], $repo->marked);
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='t_new'")->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='t_old'")->fetchColumn());

        @unlink($oldFile);
        @unlink($newFile);
        @rmdir($dir);
    }

    public function testRollbackUsesStepAndMarksRolledBack(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $dir = sys_get_temp_dir() . '/rowcast_runner_rb_' . uniqid('', true);
        mkdir($dir, 0o777, true);

        $class1 = 'Migration_' . str_replace('.', '', uniqid('20260102_000001_', true));
        $class2 = 'Migration_' . str_replace('.', '', uniqid('20260102_000002_', true));
        $file1 = $this->writeMigrationFile(
            $dir,
            $class1,
            '$schema->createTable(\'r1\', static function ($t): void { $t->column(\'id\', \'integer\')->primaryKey(); });',
            '$schema->dropTable(\'r1\');',
        );
        $file2 = $this->writeMigrationFile(
            $dir,
            $class2,
            '$schema->createTable(\'r2\', static function ($t): void { $t->column(\'id\', \'integer\')->primaryKey(); });',
            '$schema->dropTable(\'r2\');',
        );

        $repo = new class () implements MigrationRepositoryInterface {
            /** @var list<string> */
            private array $applied = [];
            /** @var list<string> */
            public array $rolledBack = [];

            public function ensureTable(): void
            {
            }
            public function getApplied(): array
            {
                return $this->applied;
            }
            public function markApplied(string $version): void
            {
                $this->applied[] = $version;
            }
            public function markRolledBack(string $version): void
            {
                $this->rolledBack[] = $version;
                $this->applied = array_values(array_filter(
                    $this->applied,
                    static fn (string $item): bool => $item !== $version,
                ));
            }
        };

        $runner = new MigrationRunner($pdo, new MigrationLoader(), $repo, $this->sqlitePlatform());
        $runner->migrate($dir);
        $count = $runner->rollback($dir, 1);

        self::assertSame(1, $count);
        self::assertSame([$class2], $repo->rolledBack);
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='r1'")->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='r2'")->fetchColumn());

        @unlink($file1);
        @unlink($file2);
        @rmdir($dir);
    }

    public function testThrowsWhenMigrationClassMissing(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $dir = sys_get_temp_dir() . '/rowcast_runner_missing_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $class = 'Migration_' . str_replace('.', '', uniqid('20260103_000001_', true));
        $file = $dir . '/' . $class . '.php';
        file_put_contents($file, "<?php\n");

        $repo = new class () implements MigrationRepositoryInterface {
            public function ensureTable(): void
            {
            }
            public function getApplied(): array
            {
                return [];
            }
            public function markApplied(string $version): void
            {
            }
            public function markRolledBack(string $version): void
            {
            }
        };

        $runner = new MigrationRunner($pdo, new MigrationLoader(), $repo, $this->sqlitePlatform());

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage(\sprintf('Migration class "%s" was not found', $class));
            $runner->migrate($dir);
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }

    public function testExecutesInsideTransactionForTransactionalPlatform(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tx_log (value TEXT NOT NULL)');

        $dir = sys_get_temp_dir() . '/rowcast_runner_tx_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $class = 'Migration_' . str_replace('.', '', uniqid('20260104_000001_', true));
        $file = $this->writeMigrationFile($dir, $class, '$schema->dropTable(\'any\');');

        $repo = new class () implements MigrationRepositoryInterface {
            public function ensureTable(): void
            {
            }
            public function getApplied(): array
            {
                return [];
            }
            public function markApplied(string $version): void
            {
            }
            public function markRolledBack(string $version): void
            {
            }
        };

        $platform = new class ($pdo) implements PlatformInterface {
            public function __construct(private \PDO $pdo)
            {
            }
            public function toSql(OperationInterface $operation): array
            {
                $tx = $this->pdo->inTransaction() ? '1' : '0';
                return ["INSERT INTO tx_log(value) VALUES ('in_tx={$tx}')"];
            }
            public function supportsDdlTransactions(): bool
            {
                return true;
            }
        };

        $runner = new MigrationRunner($pdo, new MigrationLoader(), $repo, $platform);
        $runner->migrate($dir);

        $value = $pdo->query('SELECT value FROM tx_log LIMIT 1')->fetchColumn();
        self::assertSame('in_tx=1', $value);

        @unlink($file);
        @rmdir($dir);
    }

    private function sqlitePlatform(): PlatformInterface
    {
        return new \AsceticSoft\RowcastSchema\Platform\SqlitePlatform(new \AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper());
    }

    private function writeMigrationFile(string $dir, string $className, string $upCode, string $downCode = ''): string
    {
        $path = $dir . '/' . $className . '.php';
        $downMethod = $downCode !== ''
            ? "public function down(\\AsceticSoft\\RowcastSchema\\SchemaBuilder\\SchemaBuilder \$schema): void { {$downCode} }"
            : '';

        file_put_contents($path, <<<PHP
            <?php
            declare(strict_types=1);

            final class {$className} extends \\AsceticSoft\\RowcastSchema\\Migration\\AbstractMigration
            {
                public function up(\\AsceticSoft\\RowcastSchema\\SchemaBuilder\\SchemaBuilder \$schema): void
                {
                    {$upCode}
                }

                {$downMethod}
            }
            PHP);

        return $path;
    }
}
