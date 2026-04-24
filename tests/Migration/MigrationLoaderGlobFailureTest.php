<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Migration;

use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use PHPUnit\Framework\TestCase;

final class MigrationLoaderGlobFailureTest extends TestCase
{
    public function testReturnsEmptyArrayWhenGlobFails(): void
    {
        $dir = sys_get_temp_dir() . '/rowcast_loader_glob_' . uniqid('', true);
        mkdir($dir, 0o777, true);

        try {
            $loader = new MigrationLoader(static fn (string $pattern): array|false => false);

            self::assertSame([], $loader->load($dir));
        } finally {
            @rmdir($dir);
        }
    }
}
