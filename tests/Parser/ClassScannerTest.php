<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Parser\ClassScanner;
use PHPUnit\Framework\TestCase;

final class ClassScannerTest extends TestCase
{
    public function testScansClassesFromDirectory(): void
    {
        $dir = $this->createTempDirectory();
        mkdir($dir . '/Nested');

        file_put_contents($dir . '/User.php', <<<'PHP'
            <?php
            namespace Demo\App;

            final class User {}
            PHP);
        file_put_contents($dir . '/Nested/Post.php', <<<'PHP'
            <?php
            namespace Demo\App\Nested;

            class Post {}
            PHP);
        file_put_contents($dir . '/Anonymous.php', <<<'PHP'
            <?php
            namespace Demo\App;

            $value = new class () {};
            PHP);

        try {
            $classes = new ClassScanner()->scan($dir);

            sort($classes);
            self::assertSame(
                ['Demo\\App\\Nested\\Post', 'Demo\\App\\User'],
                $classes,
            );
        } finally {
            $this->deleteDirectory($dir);
        }
    }

    public function testThrowsWhenPathIsNotDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema attribute path is not a directory:');

        new ClassScanner()->scan(__FILE__);
    }

    private function createTempDirectory(): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'rowcast_scanner_');
        if (!is_string($temp)) {
            self::fail('Failed to create temp path.');
        }

        unlink($temp);
        mkdir($temp);

        return $temp;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
                continue;
            }
            unlink($file->getPathname());
        }

        rmdir($path);
    }
}
