<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff;

use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\ReferentialAction;

final class ForeignKeyComparator
{
    public function areEqual(ForeignKey $a, ForeignKey $b): bool
    {
        return $a->name === $b->name
            && $a->columns === $b->columns
            && $a->referenceTable === $b->referenceTable
            && $a->referenceColumns === $b->referenceColumns
            && $this->normalizeReferentialAction($a->onDelete) === $this->normalizeReferentialAction($b->onDelete)
            && $this->normalizeReferentialAction($a->onUpdate) === $this->normalizeReferentialAction($b->onUpdate);
    }

    /**
     * Align introspection (NO ACTION -> null) with schema files (explicit NO ACTION -> enum)
     * and enum vs equivalent string literals from migrations.
     *
     * @return ReferentialAction|string|null
     */
    private function normalizeReferentialAction(ReferentialAction|string|null $value): ReferentialAction|string|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ReferentialAction) {
            return $value === ReferentialAction::NoAction ? null : $value;
        }

        $resolved = ReferentialAction::tryFromString($value);

        if ($resolved instanceof ReferentialAction) {
            return $resolved === ReferentialAction::NoAction ? null : $resolved;
        }

        return $resolved;
    }
}
