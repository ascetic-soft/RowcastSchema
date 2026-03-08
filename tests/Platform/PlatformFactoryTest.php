<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Platform;

use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Platform\PlatformFactory;
use AsceticSoft\RowcastSchema\Platform\PlatformInterface;
use AsceticSoft\RowcastSchema\Platform\SqlitePlatform;
use PHPUnit\Framework\TestCase;

final class PlatformFactoryTest extends TestCase
{
    public function testCreatesDefaultPlatformForSqlitePdo(): void
    {
        $factory = new PlatformFactory();
        $platform = $factory->createForPdo(new \PDO('sqlite::memory:'));

        self::assertInstanceOf(SqlitePlatform::class, $platform);
    }

    public function testUsesCustomRegistryEntry(): void
    {
        $custom = new class () implements PlatformInterface {
            public function toSql(OperationInterface $operation): array
            {
                return [];
            }

            public function supportsDdlTransactions(): bool
            {
                return true;
            }
        };

        $factory = new PlatformFactory([
            'sqlite' => static fn (): PlatformInterface => $custom,
        ]);

        self::assertSame($custom, $factory->createForPdo(new \PDO('sqlite::memory:')));
    }

}
