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
}
