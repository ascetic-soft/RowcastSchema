<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli;

use AsceticSoft\RowcastSchema\Cli\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testLoadsConfigFromArrayFile(): void
    {
        $file = $this->writeConfigFile(<<<'PHP'
            <?php
            return [
                'schema' => __DIR__ . '/schema.php',
                'migrations' => __DIR__ . '/migrations',
                'migration_table' => 'migrations_log',
                'connection' => [
                    'dsn' => 'sqlite::memory:',
                ],
                'ignore_tables' => ['/^tmp_/'],
            ];
            PHP);

        try {
            $config = Config::fromFile($file);
            self::assertSame('migrations_log', $config->migrationTableName);
            self::assertSame(1, \count($config->ignoreTableRules));
            self::assertInstanceOf(\PDO::class, $config->pdo);
        } finally {
            @unlink($file);
        }
    }

    public function testLoadsConfigFromClosureFile(): void
    {
        $file = $this->writeConfigFile(<<<'PHP'
            <?php
            return static function (string $cwd): array {
                return [
                    'schema' => $cwd . '/schema.php',
                    'migrations' => $cwd . '/migrations',
                    'connection' => ['dsn' => 'sqlite::memory:'],
                    'ignore_tables' => [
                        static fn (string $table): bool => str_starts_with($table, 'tmp_'),
                    ],
                ];
            };
            PHP);

        try {
            $config = Config::fromFile($file);
            self::assertNotEmpty($config->schemaPath);
            self::assertNotEmpty($config->migrationsPath);
            self::assertCount(1, $config->ignoreTableRules);
            self::assertInstanceOf(\Closure::class, $config->ignoreTableRules[0]);
        } finally {
            @unlink($file);
        }
    }

    public function testThrowsWhenConfigFileMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config file not found');
        Config::fromFile('/tmp/rowcast_missing_' . uniqid('', true) . '.php');
    }

    public function testThrowsWhenDsnIsMissing(): void
    {
        $file = $this->writeConfigFile(<<<'PHP'
            <?php
            return [
                'connection' => [],
            ];
            PHP);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Connection "dsn" is required.');
            Config::fromFile($file);
        } finally {
            @unlink($file);
        }
    }

    public function testThrowsWhenIgnoreRegexIsInvalid(): void
    {
        $file = $this->writeConfigFile(<<<'PHP'
            <?php
            return [
                'connection' => ['dsn' => 'sqlite::memory:'],
                'ignore_tables' => ['/[invalid/'],
            ];
            PHP);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Invalid ignore table regex pattern');
            Config::fromFile($file);
        } finally {
            @unlink($file);
        }
    }

    private function writeConfigFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/rowcast_config_' . uniqid('', true) . '.php';
        file_put_contents($path, $content);

        return $path;
    }
}
