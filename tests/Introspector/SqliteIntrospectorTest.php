<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Introspector;

use AsceticSoft\RowcastSchema\Introspector\SqliteIntrospector;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;
use PHPUnit\Framework\TestCase;

final class SqliteIntrospectorTest extends TestCase
{
    public function testIntrospectsTablesColumnsAndPrimaryKeys(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL, is_active INTEGER DEFAULT 1)');
        $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT)');

        $schema = new SqliteIntrospector(new SqliteTypeMapper())->introspect($pdo);

        self::assertTrue($schema->hasTable('users'));
        self::assertTrue($schema->hasTable('posts'));

        $users = $schema->getTable('users');
        self::assertNotNull($users);
        self::assertSame(['id'], $users->primaryKey);
        $id = $users->getColumn('id');
        self::assertNotNull($id);
        self::assertSame(ColumnType::Integer, $id->type);
        self::assertTrue($id->primaryKey);
        self::assertTrue($id->nullable);

        $email = $users->getColumn('email');
        self::assertNotNull($email);
        self::assertSame(ColumnType::Text, $email->type);
        self::assertFalse($email->nullable);

        $isActive = $users->getColumn('is_active');
        self::assertNotNull($isActive);
        self::assertSame('1', (string) $isActive->default);
    }
}
