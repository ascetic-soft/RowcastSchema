<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Introspector;

use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Introspector\SqliteIntrospector;
use PHPUnit\Framework\TestCase;

final class IntrospectorFactoryTest extends TestCase
{
    public function testCreatesDefaultIntrospectorForSqlitePdo(): void
    {
        $factory = new IntrospectorFactory();
        $introspector = $factory->createForPdo(new \PDO('sqlite::memory:'));

        self::assertInstanceOf(SqliteIntrospector::class, $introspector);
    }
}
