<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Introspector;

use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorInterface;
use AsceticSoft\RowcastSchema\Introspector\SqliteIntrospector;
use AsceticSoft\RowcastSchema\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class IntrospectorFactoryTest extends TestCase
{
    public function testCreatesDefaultIntrospectorForSqlitePdo(): void
    {
        $factory = new IntrospectorFactory();
        $introspector = $factory->createForPdo(new \PDO('sqlite::memory:'));

        self::assertInstanceOf(SqliteIntrospector::class, $introspector);
    }

    public function testUsesCustomRegistryEntry(): void
    {
        $custom = new class () implements IntrospectorInterface {
            public function introspect(\PDO $pdo): Schema
            {
                return new Schema();
            }
        };

        $factory = new IntrospectorFactory([
            'sqlite' => static fn (): IntrospectorInterface => $custom,
        ]);

        self::assertSame($custom, $factory->createForPdo(new \PDO('sqlite::memory:')));
    }

}
