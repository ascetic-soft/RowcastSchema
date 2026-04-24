<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli;

use AsceticSoft\RowcastSchema\Cli\PdoFactory;
use PHPUnit\Framework\TestCase;

final class PdoFactoryTest extends TestCase
{
    public function testCreatesPdoAndSetsExceptionMode(): void
    {
        $factory = new PdoFactory();

        $pdo = $factory->create([
            'dsn' => 'sqlite::memory:',
            'options' => 'invalid',
        ]);

        self::assertInstanceOf(\PDO::class, $pdo);
        self::assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }

    public function testThrowsWhenDsnIsMissing(): void
    {
        $factory = new PdoFactory();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection "dsn" is required.');
        $factory->create([]);
    }
}
