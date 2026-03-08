<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Command\MakeCommand;
use AsceticSoft\RowcastSchema\Cli\ConsoleOutput;
use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use PHPUnit\Framework\TestCase;

final class MakeCommandTest extends TestCase
{
    public function testGeneratesEmptyMigrationFile(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $dir = sys_get_temp_dir() . '/rowcast_make_cmd_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $config = new Config('schema.php', $dir, '_rowcast_migrations', $pdo);

        ob_start();
        $code = new MakeCommand(
            new MigrationGenerator(),
            new ConsoleOutput(noAnsi: true),
        )->execute([], $config);
        $out = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Rowcast Schema -- make', $out);
        self::assertStringContainsString('Empty migration generated:', $out);

        $files = glob($dir . '/Migration_*.php');
        self::assertIsArray($files);
        self::assertCount(1, $files);

        if ($files !== []) {
            @unlink($files[0]);
        }
        @rmdir($dir);
    }
}
