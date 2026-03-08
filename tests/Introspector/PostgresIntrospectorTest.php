<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Introspector;

use AsceticSoft\RowcastSchema\Introspector\PostgresIntrospector;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\TypeMapper\PostgresTypeMapper;
use PHPUnit\Framework\TestCase;

final class PostgresIntrospectorTest extends TestCase
{
    public function testIntrospectsPostgresColumnsIndexesAndForeignKeys(): void
    {
        $pdo = new class () extends \PDO {
            private \PDO $inner;
            public function __construct()
            {
                parent::__construct('sqlite::memory:');
                $this->inner = new \PDO('sqlite::memory:');
                $this->inner->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                $this->inner->exec(
                    'CREATE TABLE pg_columns (
                        table_name TEXT, column_name TEXT, udt_name TEXT, is_nullable TEXT, column_default TEXT,
                        character_maximum_length TEXT, numeric_precision TEXT, numeric_scale TEXT
                    )',
                );
                $this->inner->exec(
                    "INSERT INTO pg_columns VALUES
                        ('users','id','int4','NO','nextval(''users_id_seq''::regclass)',NULL,NULL,NULL),
                        ('users','email','varchar','NO',NULL,'255',NULL,NULL),
                        ('users','is_active','bool','NO','true',NULL,NULL,NULL),
                        ('orders','amount','numeric','YES','10.50',NULL,'10','2')",
                );

                $this->inner->exec('CREATE TABLE pg_pk (table_name TEXT, column_name TEXT, ordinal_position INTEGER)');
                $this->inner->exec("INSERT INTO pg_pk VALUES ('users','id',1)");

                $this->inner->exec(
                    'CREATE TABLE pg_indexes (table_name TEXT, index_name TEXT, is_unique TEXT, column_name TEXT, column_position INTEGER)',
                );
                $this->inner->exec("INSERT INTO pg_indexes VALUES ('users','idx_users_email','t','email',1)");

                $this->inner->exec(
                    'CREATE TABLE pg_fks (
                        table_name TEXT, constraint_name TEXT, column_name TEXT,
                        reference_table_name TEXT, reference_column_name TEXT,
                        delete_rule TEXT, update_rule TEXT, ordinal_position INTEGER
                    )',
                );
                $this->inner->exec(
                    "INSERT INTO pg_fks VALUES ('users','fk_users_account','id','accounts','id','CASCADE','NO ACTION',1)",
                );
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
            {
                if (str_contains($query, 'FROM information_schema.columns c')) {
                    return $this->inner->query('SELECT * FROM pg_columns ORDER BY table_name, column_name');
                }
                if (str_contains($query, "constraint_type = 'PRIMARY KEY'")) {
                    return $this->inner->query('SELECT * FROM pg_pk ORDER BY table_name, ordinal_position');
                }
                if (str_contains($query, 'FROM pg_class t')) {
                    return $this->inner->query('SELECT * FROM pg_indexes ORDER BY table_name, index_name, column_position');
                }
                if (str_contains($query, "constraint_type = 'FOREIGN KEY'")) {
                    return $this->inner->query('SELECT * FROM pg_fks ORDER BY table_name, constraint_name, ordinal_position');
                }

                return parent::query($query, $fetchMode, ...$fetchModeArgs);
            }
        };

        $schema = new PostgresIntrospector(new PostgresTypeMapper())->introspect($pdo);

        $users = $schema->getTable('users');
        self::assertNotNull($users);
        self::assertSame(['id'], $users->primaryKey);

        $id = $users->getColumn('id');
        self::assertNotNull($id);
        self::assertSame(ColumnType::Integer, $id->type);
        self::assertTrue($id->autoIncrement);

        $email = $users->getColumn('email');
        self::assertNotNull($email);
        self::assertSame(ColumnType::String, $email->type);
        self::assertSame(255, $email->length);

        $isActive = $users->getColumn('is_active');
        self::assertNotNull($isActive);
        self::assertSame(true, $isActive->default);

        self::assertArrayHasKey('idx_users_email', $users->indexes);
        self::assertArrayHasKey('fk_users_account', $users->foreignKeys);

        $orders = $schema->getTable('orders');
        self::assertNotNull($orders);
        $amount = $orders->getColumn('amount');
        self::assertNotNull($amount);
        self::assertSame(ColumnType::Decimal, $amount->type);
        self::assertSame(10, $amount->precision);
        self::assertSame(2, $amount->scale);
        self::assertSame(10.5, $amount->default);
    }
}
