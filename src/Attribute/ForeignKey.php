<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Attribute;

use AsceticSoft\RowcastSchema\Schema\ReferentialAction;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class ForeignKey
{
    /**
     * @param list<string> $referenceColumns
     * @param list<string> $columns
     */
    public function __construct(
        public string $name,
        public string $referenceTable,
        public array $referenceColumns,
        public array $columns = [],
        public ReferentialAction|string|null $onDelete = null,
        public ReferentialAction|string|null $onUpdate = null,
    ) {
    }
}
