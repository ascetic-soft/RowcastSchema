<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli;

use AsceticSoft\RowcastSchema\Cli\ConfigNormalizer;
use PHPUnit\Framework\TestCase;

final class ConfigNormalizerTest extends TestCase
{
    public function testNormalizesDefaultsAndCallableIgnoreRules(): void
    {
        $normalizer = new ConfigNormalizer();

        $result = $normalizer->normalize([
            'connection' => ['dsn' => 'sqlite::memory:'],
            'ignore_tables' => [
                '/^tmp_/',
                static fn (string $table): bool => str_ends_with($table, '_shadow'),
            ],
        ], '/app');

        self::assertSame('/app/schema.php', $result['schemaPath']);
        self::assertSame('/app/migrations', $result['migrationsPath']);
        self::assertSame('_rowcast_migrations', $result['migrationTableName']);
        self::assertSame('sqlite::memory:', $result['connection']['dsn']);
        self::assertCount(2, $result['ignoreTableRules']);
        self::assertIsString($result['ignoreTableRules'][0]);
        self::assertInstanceOf(\Closure::class, $result['ignoreTableRules'][1]);
    }

    public function testKeepsExplicitSchemaAndMigrationsPaths(): void
    {
        $normalizer = new ConfigNormalizer();

        $result = $normalizer->normalize([
            'schema' => '/custom/schema.yaml',
            'migrations' => '/custom/migrations',
            'connection' => ['dsn' => 'sqlite::memory:'],
        ], '/app');

        self::assertSame('/custom/schema.yaml', $result['schemaPath']);
        self::assertSame('/custom/migrations', $result['migrationsPath']);
    }

    public function testThrowsWhenConnectionMappingIsMissing(): void
    {
        $normalizer = new ConfigNormalizer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config must contain "connection" mapping.');
        $normalizer->normalize([], '/app');
    }

    public function testThrowsWhenMigrationTableIsInvalid(): void
    {
        $normalizer = new ConfigNormalizer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config "migration_table" must be a non-empty string.');
        $normalizer->normalize([
            'connection' => ['dsn' => 'sqlite::memory:'],
            'migration_table' => '',
        ], '/app');
    }

    public function testThrowsWhenIgnoreTablesIsNotArray(): void
    {
        $normalizer = new ConfigNormalizer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config "ignore_tables" must be an array.');
        $normalizer->normalize([
            'connection' => ['dsn' => 'sqlite::memory:'],
            'ignore_tables' => 'tmp',
        ], '/app');
    }

    public function testThrowsWhenIgnoreRuleRegexIsInvalid(): void
    {
        $normalizer = new ConfigNormalizer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid ignore table regex pattern');
        $normalizer->normalize([
            'connection' => ['dsn' => 'sqlite::memory:'],
            'ignore_tables' => ['/[invalid/'],
        ], '/app');
    }

    public function testThrowsWhenIgnoreRuleRegexIsEmpty(): void
    {
        $normalizer = new ConfigNormalizer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ignore table regex rule must be a non-empty string.');
        $normalizer->normalize([
            'connection' => ['dsn' => 'sqlite::memory:'],
            'ignore_tables' => [''],
        ], '/app');
    }

    public function testThrowsWhenIgnoreRuleHasUnsupportedType(): void
    {
        $normalizer = new ConfigNormalizer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Each "ignore_tables" rule must be a regex string or callable.');
        $normalizer->normalize([
            'connection' => ['dsn' => 'sqlite::memory:'],
            'ignore_tables' => [123],
        ], '/app');
    }
}
