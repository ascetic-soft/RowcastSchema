<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Attribute\ForeignKey as ForeignKeyAttribute;
use AsceticSoft\RowcastSchema\Attribute\Index as IndexAttribute;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;

final readonly class AttributeIndexParser
{
    public function parseIndex(IndexAttribute $attr, ?string $defaultColumn = null): Index
    {
        if ($attr->columns === [] && $defaultColumn === null) {
            throw new \InvalidArgumentException(\sprintf('Index "%s" columns must be list.', $attr->name));
        }

        $columns = $attr->columns !== []
            ? $this->toStringList($attr->columns, \sprintf('Index "%s" columns must be list.', $attr->name))
            : [$defaultColumn ?? throw new \LogicException('defaultColumn must be set when attribute columns is empty.')];

        return new Index(name: $attr->name, columns: $columns, unique: $attr->unique);
    }

    public function parseForeignKey(ForeignKeyAttribute $attr, ?string $defaultColumn = null): ForeignKey
    {
        if ($attr->columns === [] && $defaultColumn === null) {
            throw new \InvalidArgumentException(\sprintf('Foreign key "%s" columns must be lists.', $attr->name));
        }

        $columns = $attr->columns !== []
            ? $this->toStringList($attr->columns, \sprintf('Foreign key "%s" columns must be lists.', $attr->name))
            : [$defaultColumn ?? throw new \LogicException('defaultColumn must be set when attribute columns is empty.')];

        return new ForeignKey(
            name: $attr->name,
            columns: $columns,
            referenceTable: $attr->referenceTable,
            referenceColumns: $this->toStringList(
                $attr->referenceColumns,
                \sprintf('Foreign key "%s" columns must be lists.', $attr->name),
            ),
            onDelete: $attr->onDelete,
            onUpdate: $attr->onUpdate,
        );
    }

    /**
     * @param mixed[] $value
     *
     * @return list<string>
     */
    private function toStringList(array $value, string $message): array
    {
        $result = [];
        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw new \InvalidArgumentException($message);
            }
            $result[] = $item;
        }

        return $result;
    }
}
