<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

final class SchemaParserFactory
{
    public function create(string $schemaPath): SchemaParserInterface
    {
        if (is_dir($schemaPath)) {
            return new AttributeSchemaParser();
        }

        $extension = strtolower(pathinfo($schemaPath, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => new PhpSchemaParser(),
            'yaml', 'yml' => new YamlSchemaParser(),
            default => throw new \InvalidArgumentException(\sprintf(
                'Unsupported schema file extension "%s". Use .php, .yaml, or .yml.',
                $extension !== '' ? $extension : '(none)',
            )),
        };
    }
}
