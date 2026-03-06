<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli;

use AsceticSoft\RowcastSchema\Cli\TableIgnoreMatcher;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use PHPUnit\Framework\TestCase;

final class TableIgnoreMatcherTest extends TestCase
{
    public function testAlwaysIgnoresMigrationTable(): void
    {
        $matcher = new TableIgnoreMatcher();

        self::assertTrue($matcher->shouldIgnore('_rowcast_migrations'));
    }

    public function testIgnoresConfiguredMigrationTableName(): void
    {
        $matcher = new TableIgnoreMatcher([], 'schema_migrations');

        self::assertTrue($matcher->shouldIgnore('schema_migrations'));
        self::assertFalse($matcher->shouldIgnore('_rowcast_migrations'));
    }

    public function testFiltersSchemaByRegexRulesAndCallbackRules(): void
    {
        $matcher = new TableIgnoreMatcher(
            rules: [
                '/^tmp_/',
                '/^audit_/',
                static fn (string $table): bool => str_ends_with($table, '_shadow'),
            ],
        );

        $schema = new Schema([
            '_rowcast_migrations' => $this->table('_rowcast_migrations'),
            'tmp_users' => $this->table('tmp_users'),
            'audit_log' => $this->table('audit_log'),
            'products_shadow' => $this->table('products_shadow'),
            'products' => $this->table('products'),
        ]);

        $filtered = $matcher->filterSchema($schema);

        self::assertFalse($filtered->hasTable('_rowcast_migrations'));
        self::assertFalse($filtered->hasTable('tmp_users'));
        self::assertFalse($filtered->hasTable('audit_log'));
        self::assertFalse($filtered->hasTable('products_shadow'));
        self::assertTrue($filtered->hasTable('products'));
    }

    private function table(string $name): Table
    {
        return new Table(
            name: $name,
            columns: [
                'id' => new Column(name: 'id', type: ColumnType::Integer),
            ],
            primaryKey: ['id'],
        );
    }
}
