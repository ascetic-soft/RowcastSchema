<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Introspector;

use AsceticSoft\RowcastSchema\Introspector\MysqlIntrospector;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\TypeMapper\MysqlTypeMapper;
use PHPUnit\Framework\TestCase;

final class MysqlIntrospectorTest extends TestCase
{
    public function testIntrospectsMysqlColumnsAndPrimaryKeyMeta(): void
    {
        $pdo = new class () extends \PDO {
            private \PDO $inner;
            public function __construct()
            {
                parent::__construct('sqlite::memory:');
                $this->inner = new \PDO('sqlite::memory:');
                $this->inner->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                $this->inner->exec('CREATE TABLE db_name (db TEXT)');
                $this->inner->exec("INSERT INTO db_name(db) VALUES ('test_db')");

                $this->inner->exec('CREATE TABLE mysql_columns (
                    TABLE_NAME TEXT, COLUMN_NAME TEXT, COLUMN_TYPE TEXT, IS_NULLABLE TEXT,
                    COLUMN_DEFAULT TEXT, COLUMN_KEY TEXT, EXTRA TEXT
                )');
                $this->inner->exec(
                    "INSERT INTO mysql_columns VALUES
                        ('users','id','int(11)','NO',NULL,'PRI','auto_increment'),
                        ('users','email','varchar(255)','NO',NULL,'',''),
                        ('users','is_active','tinyint(1)','NO','1','','')",
                );
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
            {
                if (str_contains($query, 'SELECT DATABASE()')) {
                    return $this->inner->query('SELECT db FROM db_name LIMIT 1');
                }

                return parent::query($query, $fetchMode, ...$fetchModeArgs);
            }

            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                if (str_contains($query, 'INFORMATION_SCHEMA.COLUMNS')) {
                    return $this->inner->prepare('SELECT * FROM mysql_columns WHERE :schema IS NOT NULL ORDER BY TABLE_NAME, ROWID');
                }

                return parent::prepare($query, $options);
            }
        };

        $schema = new MysqlIntrospector(new MysqlTypeMapper())->introspect($pdo);

        $users = $schema->getTable('users');
        self::assertNotNull($users);
        self::assertSame(['id'], $users->primaryKey);

        $id = $users->getColumn('id');
        self::assertNotNull($id);
        self::assertSame(ColumnType::Integer, $id->type);
        self::assertTrue($id->primaryKey);
        self::assertTrue($id->autoIncrement);

        $email = $users->getColumn('email');
        self::assertNotNull($email);
        self::assertSame(ColumnType::String, $email->type);
        self::assertSame(255, $email->length);

        self::assertFalse($schema->hasTable('orders'));
    }
}
