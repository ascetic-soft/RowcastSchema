<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Parser\ArraySchemaBuilder;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use PHPUnit\Framework\TestCase;

final class ArraySchemaBuilderTest extends TestCase
{
    public function testBuildsSchemaFromArrayWithAliasesAndConstraints(): void
    {
        $schema = new ArraySchemaBuilder()->build([
            'tables' => [
                'events' => [
                    'columns' => [
                        'id' => [
                            'type' => 'integer',
                            'primaryKey' => true,
                            'autoIncrement' => true,
                        ],
                        'payload' => [
                            'type' => 'jsonb',
                        ],
                        'occurred_at' => [
                            'type' => 'timestamp with time zone',
                        ],
                        'amount' => [
                            'type' => 'decimal',
                            'precision' => '10',
                            'scale' => '2',
                        ],
                    ],
                    'indexes' => [
                        'idx_events_payload' => [
                            'columns' => ['payload'],
                            'unique' => true,
                        ],
                    ],
                    'foreignKeys' => [
                        'fk_events_user' => [
                            'columns' => ['id'],
                            'references' => [
                                'table' => 'users',
                                'columns' => ['id'],
                            ],
                            'onDelete' => 'CASCADE',
                            'onUpdate' => 'RESTRICT',
                        ],
                    ],
                    'engine' => 'InnoDB',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
            ],
        ]);

        $events = $schema->getTable('events');
        self::assertNotNull($events);
        self::assertSame(['id'], $events->primaryKey);
        self::assertSame('InnoDB', $events->engine);
        self::assertSame('utf8mb4', $events->charset);
        self::assertSame('utf8mb4_unicode_ci', $events->collation);

        $payload = $events->getColumn('payload');
        self::assertNotNull($payload);
        self::assertSame(ColumnType::Json, $payload->type);

        $occurredAt = $events->getColumn('occurred_at');
        self::assertNotNull($occurredAt);
        self::assertSame(ColumnType::Timestamptz, $occurredAt->type);

        $amount = $events->getColumn('amount');
        self::assertNotNull($amount);
        self::assertSame(10, $amount->precision);
        self::assertSame(2, $amount->scale);

        self::assertArrayHasKey('idx_events_payload', $events->indexes);
        self::assertArrayHasKey('fk_events_user', $events->foreignKeys);
    }

    public function testThrowsWhenTablesSectionIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema must contain "tables" mapping.');

        new ArraySchemaBuilder()->build([]);
    }

    public function testThrowsOnUnknownColumnType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown column type "citext" for column "title".');

        new ArraySchemaBuilder()->build([
            'tables' => [
                'posts' => [
                    'columns' => [
                        'title' => ['type' => 'citext'],
                    ],
                ],
            ],
        ]);
    }
}
