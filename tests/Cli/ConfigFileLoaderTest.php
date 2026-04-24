<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli;

use AsceticSoft\RowcastSchema\Cli\ConfigFileLoader;
use PHPUnit\Framework\TestCase;

final class ConfigFileLoaderTest extends TestCase
{
    public function testLoadsArrayConfig(): void
    {
        $file = $this->writeConfigFile(<<<'PHP'
            <?php
            return ['connection' => ['dsn' => 'sqlite::memory:']];
            PHP);

        try {
            $loader = new ConfigFileLoader();
            $config = $loader->load($file, getcwd() ?: '/tmp');

            self::assertSame('sqlite::memory:', $config['connection']['dsn']);
        } finally {
            @unlink($file);
        }
    }

    public function testInvokesClosureConfigWithCwd(): void
    {
        $file = $this->writeConfigFile(<<<'PHP'
            <?php
            return static function (string $cwd): array {
                return ['schema' => $cwd . '/schema.php'];
            };
            PHP);

        try {
            $loader = new ConfigFileLoader();
            $config = $loader->load($file, '/workdir');

            self::assertSame('/workdir/schema.php', $config['schema']);
        } finally {
            @unlink($file);
        }
    }

    public function testThrowsWhenConfigFileDoesNotReturnArray(): void
    {
        $file = $this->writeConfigFile(<<<'PHP'
            <?php
            return 123;
            PHP);

        try {
            $loader = new ConfigFileLoader();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Config file must return array or Closure returning array.');
            $loader->load($file, '/workdir');
        } finally {
            @unlink($file);
        }
    }

    public function testThrowsWhenConfigFileIsMissing(): void
    {
        $loader = new ConfigFileLoader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config file not found');
        $loader->load('/tmp/rowcast_missing_' . uniqid('', true) . '.php', '/workdir');
    }

    private function writeConfigFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/rowcast_loader_' . uniqid('', true) . '.php';
        file_put_contents($path, $content);

        return $path;
    }
}
