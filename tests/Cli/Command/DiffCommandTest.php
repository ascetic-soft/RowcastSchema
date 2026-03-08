<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli\Command;

use AsceticSoft\RowcastSchema\Cli\Command\DiffCommand;
use AsceticSoft\RowcastSchema\Cli\ConsoleOutput;
use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Cli\OperationDescriber;
use AsceticSoft\RowcastSchema\Cli\TableIgnoreMatcher;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorInterface;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use PHPUnit\Framework\TestCase;

final class DiffCommandTest extends TestCase
{
    public function testDryRunPrintsNoChangesMessage(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $config = new Config('schema.php', sys_get_temp_dir(), '_rowcast_migrations', $pdo);
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

        $command = new DiffCommand(
            $parser,
            $factory,
            new SchemaDiffer(),
            new MigrationGenerator(),
            new TableIgnoreMatcher(),
            new ConsoleOutput(noAnsi: true),
            new OperationDescriber(),
        );

        ob_start();
        $code = $command->execute(['--dry-run'], $config);
        $out = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Rowcast Schema -- diff (dry-run)', $out);
        self::assertStringContainsString('No schema changes detected.', $out);
    }

    public function testGeneratesMigrationFileWhenNotDryRun(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $dir = sys_get_temp_dir() . '/rowcast_diff_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $config = new Config('schema.php', $dir, '_rowcast_migrations', $pdo);

        $parser = new class () implements SchemaParserInterface {
            public function parse(string $path): Schema
            {
                return new Schema([
                    'users' => new Table('users', ['id' => new Column('id', ColumnType::Integer, primaryKey: true)], ['id']),
                ]);
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

        $command = new DiffCommand(
            $parser,
            $factory,
            new SchemaDiffer(),
            new MigrationGenerator(),
            new TableIgnoreMatcher(),
            new ConsoleOutput(noAnsi: true),
            new OperationDescriber(),
        );

        ob_start();
        $code = $command->execute([], $config);
        $out = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Rowcast Schema -- diff', $out);
        self::assertStringContainsString('Detected 1 operation:', $out);
        self::assertStringContainsString('Migration generated:', $out);

        $files = glob($dir . '/Migration_*.php');
        self::assertIsArray($files);
        self::assertCount(1, $files);

        if ($files !== []) {
            @unlink($files[0]);
        }
        @rmdir($dir);
    }
}
