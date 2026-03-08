<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Migration;

use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use PHPUnit\Framework\TestCase;

final class MigrationLoaderTest extends TestCase
{
    public function testReturnsEmptyArrayForMissingDirectory(): void
    {
        $loader = new MigrationLoader();

        self::assertSame([], $loader->load(sys_get_temp_dir() . '/missing_' . uniqid('', true)));
    }

    public function testLoadsAndSortsMigrationFiles(): void
    {
        $dir = sys_get_temp_dir() . '/rowcast_loader_' . uniqid('', true);
        mkdir($dir, 0o777, true);

        $file2 = $dir . '/Migration_20260102_000002.php';
        $file1 = $dir . '/Migration_20260101_000001.php';
        file_put_contents($file2, "<?php\n");
        file_put_contents($file1, "<?php\n");
        file_put_contents($dir . '/not_a_migration.php', "<?php\n");

        try {
            $loaded = new MigrationLoader()->load($dir);

            self::assertSame([
                'Migration_20260101_000001' => $file1,
                'Migration_20260102_000002' => $file2,
            ], $loaded);
        } finally {
            @unlink($file1);
            @unlink($file2);
            @unlink($dir . '/not_a_migration.php');
            @rmdir($dir);
        }
    }
}
