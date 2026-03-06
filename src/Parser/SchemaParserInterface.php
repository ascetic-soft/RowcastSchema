<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Schema\Schema;

interface SchemaParserInterface
{
    public function parse(string $path): Schema;
}
