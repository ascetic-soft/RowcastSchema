<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Integration;

use AsceticSoft\RowcastSchema\Migration\DatabaseMigrationRepository;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use AsceticSoft\RowcastSchema\Platform\SqlitePlatform;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerSqliteTest extends TestCase
{
    public function testAppliesMigrationOnSqliteInMemory(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $dir = sys_get_temp_dir() . '/rowcast_schema_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $migrationFile = $dir . '/Migration_20260101_000001.php';
        file_put_contents($migrationFile, <<<'PHP'
<?php
declare(strict_types=1);

use AsceticSoft\RowcastSchema\Migration\AbstractMigration;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;
use AsceticSoft\RowcastSchema\SchemaBuilder\TableBuilder;

final class Migration_20260101_000001 extends AbstractMigration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->createTable('users', function (TableBuilder $table): void {
            $table->integer('id')->primaryKey();
            $table->string('email', 255);
        });
    }
}
PHP);

        $runner = new MigrationRunner(
            $pdo,
            new MigrationLoader(),
            new DatabaseMigrationRepository($pdo),
            new SqlitePlatform(new SqliteTypeMapper()),
        );

        $applied = $runner->migrate($dir);
        self::assertSame(1, $applied);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        self::assertSame(1, $count);

        @unlink($migrationFile);
        @rmdir($dir);
    }
}
