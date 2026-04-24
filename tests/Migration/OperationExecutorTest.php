<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Migration;

use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Migration\OperationExecutor;
use AsceticSoft\RowcastSchema\Migration\SqliteTableRebuilder;
use AsceticSoft\RowcastSchema\Platform\PlatformInterface;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;
use PHPUnit\Framework\TestCase;

final class OperationExecutorTest extends TestCase
{
    public function testExecutesSqlInsideTransactionWhenPlatformSupportsIt(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $platform = new class () implements PlatformInterface {
            public function toSql(OperationInterface $operation): array
            {
                return ['CREATE TABLE example (id INTEGER PRIMARY KEY)'];
            }

            public function supportsDdlTransactions(): bool
            {
                return true;
            }
        };

        $builder = new SchemaBuilder();
        $builder->sql('SELECT 1');

        $executor = new OperationExecutor($pdo, $platform);
        $executor->execute($builder);

        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='example'");
        self::assertNotFalse($exists);
        self::assertSame('example', $exists->fetchColumn());
    }

    public function testExecutesWithoutTransactionWhenPlatformDoesNotSupportIt(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $platform = new class () implements PlatformInterface {
            public function toSql(OperationInterface $operation): array
            {
                return ['CREATE TABLE no_tx (id INTEGER PRIMARY KEY)'];
            }

            public function supportsDdlTransactions(): bool
            {
                return false;
            }
        };

        $builder = new SchemaBuilder();
        $builder->sql('SELECT 1');

        $executor = new OperationExecutor($pdo, $platform);
        $executor->execute($builder);

        self::assertFalse($pdo->inTransaction());
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='no_tx'");
        self::assertNotFalse($exists);
        self::assertSame('no_tx', $exists->fetchColumn());
    }

    public function testDelegatesSupportedOperationToSqliteRebuilder(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

        $builder = new SchemaBuilder();
        $builder->alterColumn(
            'users',
            'name',
            new \AsceticSoft\RowcastSchema\Schema\Column(
                name: 'name',
                type: \AsceticSoft\RowcastSchema\Schema\ColumnType::String,
                length: 150,
                default: 'guest',
            ),
        );

        $platform = new class () implements PlatformInterface {
            public bool $toSqlCalled = false;

            public function toSql(OperationInterface $operation): array
            {
                $this->toSqlCalled = true;

                return ['SELECT 1'];
            }

            public function supportsDdlTransactions(): bool
            {
                return false;
            }
        };

        $executor = new OperationExecutor($pdo, $platform, new SqliteTableRebuilder());
        $executor->execute($builder);

        self::assertFalse($platform->toSqlCalled);
        $stmt = $pdo->query('SELECT name FROM users WHERE id = 1');
        self::assertNotFalse($stmt);
        self::assertSame('Alice', $stmt->fetchColumn());
    }

    public function testRollsBackTransactionWhenSqlExecutionFails(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $platform = new class () implements PlatformInterface {
            public function toSql(OperationInterface $operation): array
            {
                return ['INVALID SQL'];
            }

            public function supportsDdlTransactions(): bool
            {
                return true;
            }
        };

        $builder = new SchemaBuilder();
        $builder->sql('SELECT 1');

        $executor = new OperationExecutor($pdo, $platform);

        $this->expectException(\Throwable::class);

        try {
            $executor->execute($builder);
        } finally {
            self::assertFalse($pdo->inTransaction());
        }
    }
}
