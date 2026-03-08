<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Pdo;

use AsceticSoft\RowcastSchema\Pdo\PdoDriverResolver;
use PHPUnit\Framework\TestCase;

final class PdoDriverResolverTest extends TestCase
{
    public function testResolvesDriverNameFromPdo(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $resolver = new PdoDriverResolver();

        self::assertSame('sqlite', $resolver->resolve($pdo));
    }
}
