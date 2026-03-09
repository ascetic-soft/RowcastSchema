<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Attribute\Column as ColumnAttribute;
use AsceticSoft\RowcastSchema\Attribute\ForeignKey as ForeignKeyAttribute;
use AsceticSoft\RowcastSchema\Attribute\Index as IndexAttribute;
use AsceticSoft\RowcastSchema\Attribute\Table as TableAttribute;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;

final readonly class AttributeSchemaBuilder
{
    public function __construct(
        private NamingStrategy $namingStrategy = new NamingStrategy(),
    ) {
    }

    /**
     * @param list<string> $classNames
     */
    public function build(array $classNames): Schema
    {
        $tables = [];

        foreach ($classNames as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            $tableAttributes = $reflection->getAttributes(TableAttribute::class);
            if ($tableAttributes === []) {
                continue;
            }

            /** @var TableAttribute $tableAttribute */
            $tableAttribute = $tableAttributes[0]->newInstance();
            $tableName = $tableAttribute->name ?? $this->namingStrategy->classToTableName($reflection->getShortName());
            if ($tableName === '') {
                throw new \InvalidArgumentException(\sprintf('Table name cannot be empty for class "%s".', $className));
            }

            $columns = [];
            $primaryKey = [];
            $indexes = [];
            $foreignKeys = [];

            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                $columnAttributes = $property->getAttributes(ColumnAttribute::class);
                if ($columnAttributes === []) {
                    continue;
                }

                /** @var ColumnAttribute $columnAttribute */
                $columnAttribute = $columnAttributes[0]->newInstance();
                $columnName = $columnAttribute->name ?? $this->namingStrategy->propertyToColumnName($property->getName());
                if ($columnName === '') {
                    throw new \InvalidArgumentException(\sprintf(
                        'Column name cannot be empty for property "%s::%s".',
                        $className,
                        $property->getName(),
                    ));
                }

                [$type, $databaseType, $enumValues] = $this->resolveColumnType($columnAttribute, $property, $className);
                $nullable = $columnAttribute->nullable ?? $this->inferNullableFromProperty($property);
                $resolvedDatabaseType = $columnAttribute->databaseType ?? $databaseType;
                $length = $columnAttribute->length;
                if ($resolvedDatabaseType === null && $type === ColumnType::String && $length === null) {
                    $length = 255;
                }

                $column = new Column(
                    name: $columnName,
                    type: $type,
                    nullable: $nullable,
                    default: $this->resolveColumnDefault($columnAttribute, $property),
                    primaryKey: $columnAttribute->primaryKey,
                    autoIncrement: $columnAttribute->autoIncrement,
                    length: $length,
                    precision: $columnAttribute->precision,
                    scale: $columnAttribute->scale,
                    unsigned: $columnAttribute->unsigned,
                    comment: $columnAttribute->comment,
                    enumValues: $enumValues,
                    databaseType: $resolvedDatabaseType,
                );
                $columns[$columnName] = $column;

                if ($column->primaryKey) {
                    $primaryKey[] = $columnName;
                }

                foreach ($property->getAttributes(IndexAttribute::class) as $indexAttributeReflection) {
                    /** @var IndexAttribute $indexAttribute */
                    $indexAttribute = $indexAttributeReflection->newInstance();
                    $indexColumns = $indexAttribute->columns !== [] ? $this->toStringList(
                        $indexAttribute->columns,
                        \sprintf('Index "%s" columns must be list.', $indexAttribute->name),
                    ) : [$columnName];
                    $indexes[$indexAttribute->name] = new Index(
                        name: $indexAttribute->name,
                        columns: $indexColumns,
                        unique: $indexAttribute->unique,
                    );
                }

                foreach ($property->getAttributes(ForeignKeyAttribute::class) as $foreignKeyReflection) {
                    /** @var ForeignKeyAttribute $foreignKeyAttribute */
                    $foreignKeyAttribute = $foreignKeyReflection->newInstance();
                    $foreignKeyColumns = $foreignKeyAttribute->columns !== [] ? $this->toStringList(
                        $foreignKeyAttribute->columns,
                        \sprintf('Foreign key "%s" columns must be lists.', $foreignKeyAttribute->name),
                    ) : [$columnName];
                    $foreignKeys[$foreignKeyAttribute->name] = new ForeignKey(
                        name: $foreignKeyAttribute->name,
                        columns: $foreignKeyColumns,
                        referenceTable: $foreignKeyAttribute->referenceTable,
                        referenceColumns: $this->toStringList(
                            $foreignKeyAttribute->referenceColumns,
                            \sprintf('Foreign key "%s" columns must be lists.', $foreignKeyAttribute->name),
                        ),
                        onDelete: $foreignKeyAttribute->onDelete,
                        onUpdate: $foreignKeyAttribute->onUpdate,
                    );
                }
            }

            foreach ($reflection->getAttributes(IndexAttribute::class) as $indexAttributeReflection) {
                /** @var IndexAttribute $indexAttribute */
                $indexAttribute = $indexAttributeReflection->newInstance();
                if ($indexAttribute->columns === []) {
                    throw new \InvalidArgumentException(\sprintf(
                        'Index "%s" columns must be list.',
                        $indexAttribute->name,
                    ));
                }

                $indexes[$indexAttribute->name] = new Index(
                    name: $indexAttribute->name,
                    columns: $this->toStringList(
                        $indexAttribute->columns,
                        \sprintf('Index "%s" columns must be list.', $indexAttribute->name),
                    ),
                    unique: $indexAttribute->unique,
                );
            }

            foreach ($reflection->getAttributes(ForeignKeyAttribute::class) as $foreignKeyReflection) {
                /** @var ForeignKeyAttribute $foreignKeyAttribute */
                $foreignKeyAttribute = $foreignKeyReflection->newInstance();
                if ($foreignKeyAttribute->columns === []) {
                    throw new \InvalidArgumentException(\sprintf(
                        'Foreign key "%s" columns must be lists.',
                        $foreignKeyAttribute->name,
                    ));
                }

                $foreignKeys[$foreignKeyAttribute->name] = new ForeignKey(
                    name: $foreignKeyAttribute->name,
                    columns: $this->toStringList(
                        $foreignKeyAttribute->columns,
                        \sprintf('Foreign key "%s" columns must be lists.', $foreignKeyAttribute->name),
                    ),
                    referenceTable: $foreignKeyAttribute->referenceTable,
                    referenceColumns: $this->toStringList(
                        $foreignKeyAttribute->referenceColumns,
                        \sprintf('Foreign key "%s" columns must be lists.', $foreignKeyAttribute->name),
                    ),
                    onDelete: $foreignKeyAttribute->onDelete,
                    onUpdate: $foreignKeyAttribute->onUpdate,
                );
            }

            $tables[$tableName] = new Table(
                name: $tableName,
                columns: $columns,
                primaryKey: $primaryKey,
                indexes: $indexes,
                foreignKeys: $foreignKeys,
                engine: $tableAttribute->engine,
                charset: $tableAttribute->charset,
                collation: $tableAttribute->collation,
            );
        }

        return new Schema($tables);
    }

    /**
     * @return array{0: ColumnType, 1: ?string, 2: list<string>}
     */
    private function resolveColumnType(
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

    private function inferNullableFromProperty(\ReflectionProperty $property): bool
    {
        $type = $property->getType();
        if (!$type instanceof \ReflectionType) {
            return false;
        }

        return $type->allowsNull();
    }

    private function resolveColumnDefault(ColumnAttribute $columnAttribute, \ReflectionProperty $property): mixed
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
