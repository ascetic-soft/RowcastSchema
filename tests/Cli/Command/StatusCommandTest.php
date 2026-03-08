<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Command\StatusCommand;
use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Cli\TableIgnoreMatcher;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorInterface;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRepositoryInterface;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;
use AsceticSoft\RowcastSchema\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class StatusCommandTest extends TestCase
{
    public function testPrintsAppliedPendingAndSyncState(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $dir = sys_get_temp_dir() . '/rowcast_status_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $file1 = $dir . '/Migration_20260110_000001.php';
        $file2 = $dir . '/Migration_20260110_000002.php';
        file_put_contents($file1, "<?php\n");
        file_put_contents($file2, "<?php\n");

        $config = new Config('schema.php', $dir, '_rowcast_migrations', $pdo);
        $parser = new class () implements SchemaParserInterface {
            public function parse(string $path): Schema
            {
                return new Schema();
            }
        };
        $factory = new IntrospectorFactory([
            'sqlite' => static fn (): IntrospectorInterface => new class () implements IntrospectorInterface {
                public function introspect(\PDO $pdo): Schema
                {
                    return new Schema();
                }
            },
        ]);
        $repo = new class () implements MigrationRepositoryInterface {
            public bool $ensured = false;
            public function ensureTable(): void
            {
                $this->ensured = true;
            }
            public function getApplied(): array
            {
                return ['Migration_20260110_000001'];
            }
            public function markApplied(string $version): void
            {
            }
            public function markRolledBack(string $version): void
            {
            }
        };

        $command = new StatusCommand($parser, $factory, new SchemaDiffer(), new MigrationLoader(), $repo, new TableIgnoreMatcher());

        ob_start();
        $code = $command->execute([], $config);
        $out = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertTrue($repo->ensured);
        self::assertStringContainsString('Applied: 1', $out);
        self::assertStringContainsString('Pending: 1', $out);
        self::assertStringContainsString('Migration_20260110_000002', $out);
        self::assertStringContainsString('Schema is in sync.', $out);

        @unlink($file1);
        @unlink($file2);
        @rmdir($dir);
    }
}
