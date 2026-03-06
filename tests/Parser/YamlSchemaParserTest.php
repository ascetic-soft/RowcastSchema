<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Parser\YamlSchemaParser;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use PHPUnit\Framework\TestCase;

final class YamlSchemaParserTest extends TestCase
{
    public function testParsesYamlSchema(): void
    {
        if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            self::markTestSkipped('symfony/yaml is not installed.');
        }

        $yaml = <<<YAML
            tables:
              users:
                columns:
                  id:
                    type: integer
                    primaryKey: true
                  email:
                    type: string
                    length: 255
                  created_at:
                    type: datetime
                    default: CURRENT_TIMESTAMP
                indexes:
                  idx_users_email:
                    columns: [email]
                    unique: true
              events:
                columns:
                  occurred_at:
                    type: timestamptz
            YAML;

        $file = tempnam(sys_get_temp_dir(), 'schema_');
        if ($file === false) {
            self::fail('Failed to create temp schema file.');
        }
        file_put_contents($file, $yaml);

        try {
            $schema = new YamlSchemaParser()->parse($file);
            self::assertTrue($schema->hasTable('users'));
            $users = $schema->getTable('users');
            self::assertNotNull($users);
            self::assertTrue($users->hasColumn('email'));
            self::assertArrayHasKey('idx_users_email', $users->indexes);
            $events = $schema->getTable('events');
            self::assertNotNull($events);
            $occurredAt = $events->getColumn('occurred_at');
            self::assertNotNull($occurredAt);
            self::assertSame(ColumnType::Timestamptz, $occurredAt->type);
        } finally {
            @unlink($file);
        }
    }
}
