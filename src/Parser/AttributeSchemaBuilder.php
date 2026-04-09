<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Attribute\Column as ColumnAttribute;
use AsceticSoft\RowcastSchema\Attribute\ForeignKey as ForeignKeyAttribute;
use AsceticSoft\RowcastSchema\Attribute\Index as IndexAttribute;
use AsceticSoft\RowcastSchema\Attribute\Table as TableAttribute;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;

final readonly class AttributeSchemaBuilder
{
    public function __construct(
        private NamingStrategy $namingStrategy = new NamingStrategy(),
        private PropertyTypeResolver $typeResolver = new PropertyTypeResolver(),
        private AttributeIndexParser $indexParser = new AttributeIndexParser(),
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

            [$columns, $primaryKey, $indexes, $foreignKeys] = $this->parseProperties($reflection, $className);
            $this->parseClassLevelAttributes($reflection, $indexes, $foreignKeys);

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
     * @param \ReflectionClass<object> $reflection
     *
     * @return array{0: array<string, Column>, 1: list<string>, 2: array<string, \AsceticSoft\RowcastSchema\Schema\Index>, 3: array<string, \AsceticSoft\RowcastSchema\Schema\ForeignKey>}
     */
    private function parseProperties(\ReflectionClass $reflection, string $className): array
    {
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
            $column = $this->parseColumn($columnAttribute, $property, $className);
            $columns[$column->name] = $column;

            if ($column->primaryKey) {
                $primaryKey[] = $column->name;
            }

            foreach ($property->getAttributes(IndexAttribute::class) as $indexAttrRef) {
                /** @var IndexAttribute $indexAttr */
                $indexAttr = $indexAttrRef->newInstance();
                $indexes[$indexAttr->name] = $this->indexParser->parseIndex($indexAttr, $column->name);
            }

            foreach ($property->getAttributes(ForeignKeyAttribute::class) as $fkAttrRef) {
                /** @var ForeignKeyAttribute $fkAttr */
                $fkAttr = $fkAttrRef->newInstance();
                $foreignKeys[$fkAttr->name] = $this->indexParser->parseForeignKey($fkAttr, $column->name);
            }
        }

        return [$columns, $primaryKey, $indexes, $foreignKeys];
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param array<string, \AsceticSoft\RowcastSchema\Schema\Index> $indexes
     * @param array<string, \AsceticSoft\RowcastSchema\Schema\ForeignKey> $foreignKeys
     */
    private function parseClassLevelAttributes(\ReflectionClass $reflection, array &$indexes, array &$foreignKeys): void
    {
        foreach ($reflection->getAttributes(IndexAttribute::class) as $indexAttrRef) {
            /** @var IndexAttribute $indexAttr */
            $indexAttr = $indexAttrRef->newInstance();
            $indexes[$indexAttr->name] = $this->indexParser->parseIndex($indexAttr);
        }

        foreach ($reflection->getAttributes(ForeignKeyAttribute::class) as $fkAttrRef) {
            /** @var ForeignKeyAttribute $fkAttr */
            $fkAttr = $fkAttrRef->newInstance();
            $foreignKeys[$fkAttr->name] = $this->indexParser->parseForeignKey($fkAttr);
        }
    }

    private function parseColumn(ColumnAttribute $columnAttribute, \ReflectionProperty $property, string $className): Column
    {
        $columnName = $columnAttribute->name ?? $this->namingStrategy->propertyToColumnName($property->getName());
        if ($columnName === '') {
            throw new \InvalidArgumentException(\sprintf(
                'Column name cannot be empty for property "%s::%s".',
                $className,
                $property->getName(),
            ));
        }

        [$type, $databaseType, $enumValues] = $this->typeResolver->resolveType($columnAttribute, $property, $className);
        $nullable = $columnAttribute->nullable ?? $this->typeResolver->inferNullable($property);
        $resolvedDatabaseType = $columnAttribute->databaseType ?? $databaseType;
        if ($resolvedDatabaseType !== null) {
            $type = ColumnType::Text;
        }
        $length = $columnAttribute->length;
        if ($resolvedDatabaseType === null && $type === ColumnType::String && $length === null) {
            $length = 255;
        }

        return new Column(
            name: $columnName,
            type: $type,
            nullable: $nullable,
            default: $this->typeResolver->resolveDefault($columnAttribute, $property),
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
    }
}
