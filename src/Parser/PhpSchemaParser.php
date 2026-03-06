<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Schema\Schema;

final readonly class PhpSchemaParser implements SchemaParserInterface
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

        $parsed = require $path;
        if (!\is_array($parsed)) {
            throw new \InvalidArgumentException('Schema root must be an array.');
        }

        return $this->schemaBuilder->build($parsed);
    }
}
