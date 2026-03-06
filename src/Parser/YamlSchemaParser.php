<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlSchemaParser implements SchemaParserInterface
{
    public function __construct(
        private ArraySchemaBuilder $schemaBuilder = new ArraySchemaBuilder(),
    ) {
    }

    public function parse(string $path): Schema
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('Schema file not found: %s', $path));
        }

        if (!class_exists(Yaml::class)) {
            throw new \RuntimeException(
                'YAML parsing requires "symfony/yaml". Install it via: composer require symfony/yaml',
            );
        }

        $parsed = Yaml::parseFile($path);
        if (!\is_array($parsed)) {
            throw new \InvalidArgumentException('Schema root must be a mapping.');
        }

        return $this->schemaBuilder->build($parsed);
    }
}
