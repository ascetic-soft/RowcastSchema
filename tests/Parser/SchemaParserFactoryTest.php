<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Parser\AttributeSchemaParser;
use AsceticSoft\RowcastSchema\Parser\PhpSchemaParser;
use AsceticSoft\RowcastSchema\Parser\SchemaParserFactory;
use AsceticSoft\RowcastSchema\Parser\YamlSchemaParser;
use PHPUnit\Framework\TestCase;

final class SchemaParserFactoryTest extends TestCase
{
    public function testCreatesAttributeParserForDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/rowcast_parser_factory_' . uniqid('', true);
        mkdir($dir, 0o777, true);

        try {
            $factory = new SchemaParserFactory();

            self::assertInstanceOf(AttributeSchemaParser::class, $factory->create($dir));
        } finally {
            @rmdir($dir);
        }
    }

    public function testCreatesPhpParserForPhpFile(): void
    {
        $factory = new SchemaParserFactory();

        self::assertInstanceOf(PhpSchemaParser::class, $factory->create('/tmp/schema.php'));
    }

    public function testCreatesYamlParserForYamlFile(): void
    {
        $factory = new SchemaParserFactory();

        self::assertInstanceOf(YamlSchemaParser::class, $factory->create('/tmp/schema.yaml'));
        self::assertInstanceOf(YamlSchemaParser::class, $factory->create('/tmp/schema.yml'));
    }

    public function testThrowsForUnsupportedExtension(): void
    {
        $factory = new SchemaParserFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported schema file extension');
        $factory->create('/tmp/schema.json');
    }
}
