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

    public function testThrowsWhenDriverNameCannotBeResolved(): void
    {
        $pdo = $this->getMockBuilder(\PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttribute'])
            ->getMock();
        $pdo->method('getAttribute')->with(\PDO::ATTR_DRIVER_NAME)->willReturn('');

        $resolver = new PdoDriverResolver();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to detect PDO driver name.');
        $resolver->resolve($pdo);
    }
}
