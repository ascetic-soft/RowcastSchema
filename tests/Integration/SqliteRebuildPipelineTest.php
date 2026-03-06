<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Integration;

use AsceticSoft\RowcastSchema\Migration\DatabaseMigrationRepository;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use AsceticSoft\RowcastSchema\Platform\SqlitePlatform;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;
use PHPUnit\Framework\TestCase;

final class SqliteRebuildPipelineTest extends TestCase
{
    public function testAlterColumnUsesRebuildAndPreservesData(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

        $dir = sys_get_temp_dir() . '/rowcast_sqlite_rebuild_' . uniqid('', true);
        mkdir($dir, 0o777, true);

        $migrationFile = $dir . '/Migration_20260102_000001.php';
        file_put_contents($migrationFile, <<<'PHP'
            <?php
            declare(strict_types=1);

            use AsceticSoft\RowcastSchema\Migration\AbstractMigration;
            use AsceticSoft\RowcastSchema\Schema\Column;
            use AsceticSoft\RowcastSchema\Schema\ColumnType;
            use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

            final class Migration_20260102_000001 extends AbstractMigration
            {
                public function up(SchemaBuilder $schema): void
                {
                    $schema->alterColumn(
                        'users',
                        new Column(name: 'name', type: ColumnType::String, length: 100),
                        new Column(name: 'name', type: ColumnType::String, length: 150, default: 'guest'),
                    );
                }
            }
            PHP);

        $runner = new MigrationRunner(
            $pdo,
            new MigrationLoader(),
            new DatabaseMigrationRepository($pdo),
            new SqlitePlatform(new SqliteTypeMapper()),
        );

        $runner->migrate($dir);

        $stmt = $pdo->query('SELECT name FROM users WHERE id = 1');
        self::assertNotFalse($stmt);
        $value = $stmt->fetchColumn();
        self::assertSame('Alice', $value);

        $infoStmt = $pdo->query("PRAGMA table_info('users')");
        self::assertNotFalse($infoStmt);
        $info = $infoStmt->fetchAll(\PDO::FETCH_ASSOC);
        $nameColumn = array_values(array_filter($info, static fn (array $col): bool => $col['name'] === 'name'))[0] ?? null;
        self::assertIsArray($nameColumn);
        self::assertSame("'guest'", $nameColumn['dflt_value']);

        @unlink($migrationFile);
        @rmdir($dir);
    }

    public function testAddAndDropForeignKeyViaRebuild(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE organizations (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, org_id INTEGER)');

        $dir = sys_get_temp_dir() . '/rowcast_sqlite_fk_' . uniqid('', true);
        mkdir($dir, 0o777, true);

        $addFile = $dir . '/Migration_20260103_000001.php';
        file_put_contents($addFile, <<<'PHP'
            <?php
            declare(strict_types=1);

            use AsceticSoft\RowcastSchema\Migration\AbstractMigration;
            use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

            final class Migration_20260103_000001 extends AbstractMigration
            {
                public function up(SchemaBuilder $schema): void
                {
                    $schema->addForeignKey('users', 'fk_users_org', ['org_id'], 'organizations', ['id']);
                }
            }
            PHP);

        $runner = new MigrationRunner(
            $pdo,
            new MigrationLoader(),
            new DatabaseMigrationRepository($pdo),
            new SqlitePlatform(new SqliteTypeMapper()),
        );

        $runner->migrate($dir);

        $fkStmt = $pdo->query("PRAGMA foreign_key_list('users')");
        self::assertNotFalse($fkStmt);
        $fkRowsAfterAdd = $fkStmt->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(1, $fkRowsAfterAdd);

        $dropFile = $dir . '/Migration_20260103_000002.php';
        file_put_contents($dropFile, <<<'PHP'
            <?php
            declare(strict_types=1);

            use AsceticSoft\RowcastSchema\Migration\AbstractMigration;
            use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

            final class Migration_20260103_000002 extends AbstractMigration
            {
                public function up(SchemaBuilder $schema): void
                {
                    $schema->dropForeignKey('users', 'fk_users_org');
                }
            }
            PHP);

        $runner->migrate($dir);

        $fkStmt2 = $pdo->query("PRAGMA foreign_key_list('users')");
        self::assertNotFalse($fkStmt2);
        $fkRows = $fkStmt2->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(0, $fkRows);

        @unlink($addFile);
        @unlink($dropFile);
        @rmdir($dir);
    }
}
