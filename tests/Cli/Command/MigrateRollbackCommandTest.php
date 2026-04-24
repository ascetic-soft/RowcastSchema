<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Command\MigrateCommand;
use AsceticSoft\RowcastSchema\Cli\Command\RollbackCommand;
use AsceticSoft\RowcastSchema\Cli\ConsoleOutput;
use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Migration\DatabaseMigrationRepository;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;
use AsceticSoft\RowcastSchema\Platform\SqlitePlatform;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;
use PHPUnit\Framework\TestCase;

final class MigrateRollbackCommandTest extends TestCase
{
    public function testMigrateCommandPrintsAppliedCount(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $runner = new MigrationRunner(
            $pdo,
            new MigrationLoader(),
            new DatabaseMigrationRepository($pdo),
            new SqlitePlatform(new SqliteTypeMapper()),
        );
        $dir = sys_get_temp_dir() . '/rowcast_migrate_cmd_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $config = new Config('schema.php', $dir, '_rowcast_migrations', $pdo);

        ob_start();
        $code = new MigrateCommand($runner, new ConsoleOutput(noAnsi: true))->execute([], $config);
        $out = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Rowcast Schema -- migrate', $out);
        self::assertStringContainsString('Nothing to migrate.', $out);

        @rmdir($dir);
    }

    public function testRollbackCommandParsesStepAndPrintsCount(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $runner = new MigrationRunner(
            $pdo,
            new MigrationLoader(),
            new DatabaseMigrationRepository($pdo),
            new SqlitePlatform(new SqliteTypeMapper()),
        );
        $dir = sys_get_temp_dir() . '/rowcast_rollback_cmd_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $config = new Config('schema.php', $dir, '_rowcast_migrations', $pdo);

        ob_start();
        $code = new RollbackCommand($runner, new ConsoleOutput(noAnsi: true))->execute(['--step=3'], $config);
        $out = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Rowcast Schema -- rollback (step: 3)', $out);
        self::assertStringContainsString('Nothing to rollback.', $out);

        @rmdir($dir);
    }

    public function testMigrateCommandPrintsAppliedVersionsWhenMigrationsExist(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $runner = new MigrationRunner(
            $pdo,
            new MigrationLoader(),
            new DatabaseMigrationRepository($pdo),
            new SqlitePlatform(new SqliteTypeMapper()),
        );
        $dir = sys_get_temp_dir() . '/rowcast_migrate_apply_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $file = $dir . '/Migration_20260110_000001.php';
        file_put_contents($file, <<<'PHP'
            <?php
            declare(strict_types=1);

            use AsceticSoft\RowcastSchema\Migration\AbstractMigration;
            use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

            final class Migration_20260110_000001 extends AbstractMigration
            {
                public function up(SchemaBuilder $schema): void
                {
                    $schema->sql('CREATE TABLE test_migrate_command (id INTEGER PRIMARY KEY)');
                }
            }
            PHP);
        $config = new Config('schema.php', $dir, '_rowcast_migrations', $pdo);

        try {
            ob_start();
            $code = new MigrateCommand($runner, new ConsoleOutput(noAnsi: true))->execute([], $config);
            $out = (string) ob_get_clean();

            self::assertSame(0, $code);
            self::assertStringContainsString('Applying migrations...', $out);
            self::assertStringContainsString('[OK] Migration_20260110_000001', $out);
            self::assertStringContainsString('Applied 1 migration.', $out);
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }

    public function testRollbackCommandPrintsRolledBackVersionsAndClampsStepToOne(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $runner = new MigrationRunner(
            $pdo,
            new MigrationLoader(),
            new DatabaseMigrationRepository($pdo),
            new SqlitePlatform(new SqliteTypeMapper()),
        );
        $dir = sys_get_temp_dir() . '/rowcast_rollback_apply_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $file = $dir . '/Migration_20260111_000001.php';
        file_put_contents($file, <<<'PHP'
            <?php
            declare(strict_types=1);

            use AsceticSoft\RowcastSchema\Migration\AbstractMigration;
            use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

            final class Migration_20260111_000001 extends AbstractMigration
            {
                public function up(SchemaBuilder $schema): void
                {
                    $schema->sql('CREATE TABLE test_rollback_command (id INTEGER PRIMARY KEY)');
                }

                public function down(SchemaBuilder $schema): void
                {
                    $schema->sql('DROP TABLE test_rollback_command');
                }
            }
            PHP);
        $config = new Config('schema.php', $dir, '_rowcast_migrations', $pdo);

        try {
            $runner->migrate($dir);

            ob_start();
            $code = new RollbackCommand($runner, new ConsoleOutput(noAnsi: true))->execute(['--step=0'], $config);
            $out = (string) ob_get_clean();

            self::assertSame(0, $code);
            self::assertStringContainsString('Rowcast Schema -- rollback (step: 1)', $out);
            self::assertStringContainsString('Rolling back migrations...', $out);
            self::assertStringContainsString('[OK] Migration_20260111_000001', $out);
            self::assertStringContainsString('Rolled back 1 migration.', $out);
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }
}
