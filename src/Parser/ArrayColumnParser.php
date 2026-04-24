<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final readonly class ArrayColumnParser
{
    public function __construct(
        private ArraySchemaValueReader $valueReader = new ArraySchemaValueReader(),
    ) {
    }

    /**
     * @param array<string, mixed> $columnRaw
     */
    public function parse(string $columnName, array $columnRaw): Column
    {
        $typeRaw = $columnRaw['type'] ?? null;
        if (!\is_string($typeRaw)) {
            throw new \InvalidArgumentException(\sprintf('Column "%s" must define string "type".', $columnName));
        }

        $normalizedType = $this->normalizeColumnType($typeRaw);
        $type = ColumnType::tryFrom($normalizedType);
        $databaseType = null;
        if ($type === null) {
            $type = ColumnType::Text;
            $databaseType = \trim($typeRaw);
        }

        $enumValuesRaw = $columnRaw['values'] ?? [];
        if (!\is_array($enumValuesRaw)) {
            throw new \InvalidArgumentException(\sprintf('Column "%s" enum values must be list.', $columnName));
        }

        $length = isset($columnRaw['length']) ? $this->valueReader->toInt($columnRaw['length'], 'Column length must be integer.') : null;
        if ($type === ColumnType::String && $length === null) {
            $length = 255;
        }
        $precision = isset($columnRaw['precision']) ? $this->valueReader->toInt($columnRaw['precision'], 'Column precision must be integer.') : null;
        $scale = isset($columnRaw['scale']) ? $this->valueReader->toInt($columnRaw['scale'], 'Column scale must be integer.') : null;

        return new Column(
            name: $columnName,
            type: $type,
            nullable: (bool) ($columnRaw['nullable'] ?? false),
            default: $columnRaw['default'] ?? null,
            primaryKey: (bool) ($columnRaw['primaryKey'] ?? false),
            autoIncrement: (bool) ($columnRaw['autoIncrement'] ?? false),
            length: $length,
            precision: $precision,
            scale: $scale,
            unsigned: (bool) ($columnRaw['unsigned'] ?? false),
            comment: isset($columnRaw['comment']) ? $this->valueReader->toString($columnRaw['comment'], 'Column comment must be string.') : null,
            enumValues: $this->valueReader->toStringList($enumValuesRaw, \sprintf('Column "%s" enum values must be list.', $columnName)),
            databaseType: $databaseType,
        );
    }

    private function normalizeColumnType(string $typeRaw): string
    {
        $normalized = \strtolower(\trim($typeRaw));

        return match ($normalized) {
            'jsonb' => ColumnType::Json->value,
            'timestamp with time zone' => ColumnType::Timestamptz->value,
            default => $normalized,
        };
    }
}
