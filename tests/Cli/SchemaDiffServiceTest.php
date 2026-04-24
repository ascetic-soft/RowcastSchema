<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli;

use AsceticSoft\RowcastSchema\Cli\Config;
use AsceticSoft\RowcastSchema\Cli\SchemaDiffService;
use AsceticSoft\RowcastSchema\Cli\TableIgnoreMatcher;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorInterface;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use PHPUnit\Framework\TestCase;

final class SchemaDiffServiceTest extends TestCase
{
    public function testBuildsDiffUsingParserIntrospectorAndIgnoreMatcher(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $config = new Config(
            schemaPath: 'schema.php',
            migrationsPath: sys_get_temp_dir(),
            migrationTableName: '_rowcast_migrations',
            pdo: $pdo,
            ignoreTableRules: ['/^tmp_/'],
        );

        $parser = new class () implements SchemaParserInterface {
            public function parse(string $path): Schema
            {
                return new Schema([
                    'users' => new Table('users', ['id' => new Column('id', ColumnType::Integer, primaryKey: true)], ['id']),
                    'tmp_cache' => new Table('tmp_cache', ['id' => new Column('id', ColumnType::Integer, primaryKey: true)], ['id']),
                ]);
            }
        };

        $introspector = new class () implements IntrospectorInterface {
            public function introspect(\PDO $pdo): Schema
            {
                return new Schema();
            }
        };

        $service = new SchemaDiffService($parser, $introspector, new SchemaDiffer(), new TableIgnoreMatcher($config->ignoreTableRules));

        $operations = $service->diff($config);

        self::assertCount(1, $operations);
        self::assertInstanceOf(CreateTable::class, $operations[0]);
        self::assertSame('users', $operations[0]->table->name);
    }
}
