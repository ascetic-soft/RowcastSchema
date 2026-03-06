<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Parser\PhpSchemaParser;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use PHPUnit\Framework\TestCase;

final class PhpSchemaParserTest extends TestCase
{
    public function testParsesPhpSchema(): void
    {
        $schemaPhp = <<<'PHP'
            <?php
            return [
                'tables' => [
                    'users' => [
                        'columns' => [
                            'id' => [
                                'type' => 'integer',
                                'primaryKey' => true,
                            ],
                            'email' => [
                                'type' => 'string',
                                'length' => 255,
                            ],
                        ],
                        'indexes' => [
                            'idx_users_email' => [
                                'columns' => ['email'],
                                'unique' => true,
                            ],
                        ],
                    ],
                ],
            ];
            PHP;

        $file = tempnam(sys_get_temp_dir(), 'schema_');
        if ($file === false) {
            self::fail('Failed to create temp schema file.');
        }

        $path = $file . '.php';
        rename($file, $path);
        file_put_contents($path, $schemaPhp);

        try {
            $schema = new PhpSchemaParser()->parse($path);
            self::assertTrue($schema->hasTable('users'));
            $users = $schema->getTable('users');
            self::assertNotNull($users);
            self::assertTrue($users->hasColumn('email'));
            self::assertArrayHasKey('idx_users_email', $users->indexes);
        } finally {
            @unlink($path);
        }
    }

    public function testSupportsJsonbColumnTypeAlias(): void
    {
        $schemaPhp = <<<'PHP'
            <?php
            return [
                'tables' => [
                    'events' => [
                        'columns' => [
                            'id' => [
                                'type' => 'integer',
                                'primaryKey' => true,
                            ],
                            'payload' => [
                                'type' => 'jsonb',
                            ],
                        ],
                    ],
                ],
            ];
            PHP;

        $file = tempnam(sys_get_temp_dir(), 'schema_');
        if ($file === false) {
            self::fail('Failed to create temp schema file.');
        }

        $path = $file . '.php';
        rename($file, $path);
        file_put_contents($path, $schemaPhp);

        try {
            $schema = new PhpSchemaParser()->parse($path);
            $events = $schema->getTable('events');
            self::assertNotNull($events);
            $payload = $events->getColumn('payload');
            self::assertNotNull($payload);
            self::assertSame(ColumnType::Json, $payload->type);
            self::assertNull($payload->databaseType);
        } finally {
            @unlink($path);
        }
    }
}
