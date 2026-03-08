<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Schema\Schema;

final readonly class AttributeSchemaParser implements SchemaParserInterface
{
    public function __construct(
        private ClassScanner $classScanner = new ClassScanner(),
        private AttributeSchemaBuilder $schemaBuilder = new AttributeSchemaBuilder(),
    ) {
    }

    public function parse(string $path): Schema
    {
        $classes = $this->classScanner->scan($path);

        return $this->schemaBuilder->build($classes);
    }
}
