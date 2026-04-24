<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorInterface;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;

final readonly class SchemaDiffService
{
    public function __construct(
        private SchemaParserInterface $parser,
        private IntrospectorInterface $introspector,
        private SchemaDiffer $differ,
        private TableIgnoreMatcher $tableIgnoreMatcher,
    ) {
    }

    /**
     * @return list<OperationInterface>
     */
    public function diff(Config $config): array
    {
        $target = $this->tableIgnoreMatcher->filterSchema($this->parser->parse($config->schemaPath));
        $current = $this->tableIgnoreMatcher->filterSchema($this->introspector->introspect($config->pdo));

        return $this->differ->diff($current, $target);
    }
}
