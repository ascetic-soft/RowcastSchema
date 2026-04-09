<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Attribute\Column as ColumnAttribute;
use AsceticSoft\RowcastSchema\Schema\ColumnType;

final readonly class PropertyTypeResolver
{
    /**
     * @return array{0: ColumnType, 1: ?string, 2: list<string>}
     */
    public function resolveType(
        ColumnAttribute $columnAttribute,
        \ReflectionProperty $property,
        string $className,
    ): array {
        if ($columnAttribute->type instanceof ColumnType) {
            if ($columnAttribute->type !== ColumnType::Enum) {
                return [$columnAttribute->type, null, []];
            }

            $propertyType = $this->extractPropertyTypeName($property);
            if ($propertyType === null || !enum_exists($propertyType) || !is_subclass_of($propertyType, \BackedEnum::class)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Column "%s::%s" with type ColumnType::Enum requires a backed enum property.',
                    $className,
                    $property->getName(),
                ));
            }

            $reflectionEnum = new \ReflectionEnum($propertyType);
            if ($reflectionEnum->getBackingType()?->getName() !== 'string') {
                throw new \InvalidArgumentException(\sprintf(
                    'Column "%s::%s" with type ColumnType::Enum requires a string-backed enum property.',
                    $className,
                    $property->getName(),
                ));
            }

            return [ColumnType::Enum, null, $this->extractStringEnumValues($reflectionEnum)];
        }

        $propertyType = $this->extractPropertyTypeName($property);
        if ($propertyType === null || $propertyType === '') {
            throw new \InvalidArgumentException(\sprintf(
                'Unable to infer column type for property "%s::%s". Set #[Column(type: ...)].',
                $className,
                $property->getName(),
            ));
        }

        if (enum_exists($propertyType) && is_subclass_of($propertyType, \BackedEnum::class)) {
            $reflectionEnum = new \ReflectionEnum($propertyType);
            if ($reflectionEnum->getBackingType()?->getName() === 'string') {
                return [ColumnType::Enum, null, $this->extractStringEnumValues($reflectionEnum)];
            }

            return [ColumnType::Integer, null, []];
        }

        return match ($propertyType) {
            'int' => [ColumnType::Integer, null, []],
            'string' => [ColumnType::String, null, []],
            'bool' => [ColumnType::Boolean, null, []],
            'float' => [ColumnType::Float, null, []],
            'array' => [ColumnType::Json, null, []],
            default => $this->resolveDateTimeOrFail($propertyType, $className, $property->getName()),
        };
    }

    public function inferNullable(\ReflectionProperty $property): bool
    {
        $type = $property->getType();
        if (!$type instanceof \ReflectionType) {
            return false;
        }

        return $type->allowsNull();
    }

    public function resolveDefault(ColumnAttribute $columnAttribute, \ReflectionProperty $property): mixed
    {
        if ($columnAttribute->default !== null) {
            return $columnAttribute->default;
        }

        if (!$property->hasDefaultValue()) {
            return null;
        }

        $default = $property->getDefaultValue();
        if ($default instanceof \BackedEnum) {
            return $default->value;
        }

        if (\is_scalar($default) || $default === null) {
            return $default;
        }

        return null;
    }

    private function extractPropertyTypeName(\ReflectionProperty $property): ?string
    {
        $type = $property->getType();
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            $namedTypes = array_values(array_filter(
                $type->getTypes(),
                static fn (\ReflectionType $item): bool => $item instanceof \ReflectionNamedType && $item->getName() !== 'null',
            ));
            if (\count($namedTypes) === 1) {
                return $namedTypes[0]->getName();
            }
        }

        return null;
    }

    /**
     * @return array{0: ColumnType, 1: ?string, 2: list<string>}
     */
    private function resolveDateTimeOrFail(string $propertyType, string $className, string $propertyName): array
    {
        if (is_a($propertyType, \DateTimeInterface::class, true)) {
            return [ColumnType::Datetime, null, []];
        }

        throw new \InvalidArgumentException(\sprintf(
            'Unable to infer column type for property "%s::%s". Set #[Column(type: ...)].',
            $className,
            $propertyName,
        ));
    }

    /**
     * @param \ReflectionEnum<\BackedEnum> $enum
     *
     * @return list<string>
     */
    private function extractStringEnumValues(\ReflectionEnum $enum): array
    {
        $enumValues = [];
        /** @var list<\ReflectionEnumBackedCase> $cases */
        $cases = $enum->getCases();
        foreach ($cases as $case) {
            $value = $case->getBackingValue();
            if (!\is_string($value)) {
                continue;
            }
            $enumValues[] = $value;
        }

        return $enumValues;
    }
}
