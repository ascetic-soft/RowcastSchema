<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use Symfony\Component\Yaml\Yaml;

final class YamlSchemaParser implements SchemaParserInterface
{
    public function parse(string $path): Schema
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Schema file not found: %s', $path));
        }

        /** @var mixed $parsed */
        $parsed = Yaml::parseFile($path);
        if (!is_array($parsed)) {
            throw new \InvalidArgumentException('Schema root must be a mapping.');
        }

        $tablesRaw = $parsed['tables'] ?? null;
        if (!is_array($tablesRaw)) {
            throw new \InvalidArgumentException('Schema must contain "tables" mapping.');
        }

        $tables = [];
        foreach ($tablesRaw as $tableName => $tableRaw) {
            if (!is_string($tableName) || !is_array($tableRaw)) {
                throw new \InvalidArgumentException('Invalid table definition.');
            }
            $tables[$tableName] = $this->parseTable($tableName, $tableRaw);
        }

        return new Schema($tables);
    }

    /**
     * @param array<string, mixed> $tableRaw
     */
    private function parseTable(string $tableName, array $tableRaw): Table
    {
        $columnsRaw = $tableRaw['columns'] ?? null;
        if (!is_array($columnsRaw) || $columnsRaw === []) {
            throw new \InvalidArgumentException(sprintf('Table "%s" must define non-empty "columns".', $tableName));
        }

        $columns = [];
        $autoPrimary = [];
        foreach ($columnsRaw as $columnName => $columnRaw) {
            if (!is_string($columnName) || !is_array($columnRaw)) {
                throw new \InvalidArgumentException(sprintf('Invalid column in table "%s".', $tableName));
            }
            $column = $this->parseColumn($columnName, $columnRaw);
            $columns[$columnName] = $column;
            if ($column->primaryKey) {
                $autoPrimary[] = $columnName;
            }
        }

        $primaryKey = $autoPrimary;
        if (isset($tableRaw['primaryKey'])) {
            if (!is_array($tableRaw['primaryKey'])) {
                throw new \InvalidArgumentException(sprintf('Table "%s" primaryKey must be list.', $tableName));
            }
            $primaryKey = array_values(array_map(static fn (mixed $v): string => (string)$v, $tableRaw['primaryKey']));
        }

        $indexes = [];
        foreach (($tableRaw['indexes'] ?? []) as $indexName => $indexRaw) {
            if (!is_string($indexName) || !is_array($indexRaw)) {
                throw new \InvalidArgumentException(sprintf('Invalid index in table "%s".', $tableName));
            }
            $cols = $indexRaw['columns'] ?? [];
            if (!is_array($cols)) {
                throw new \InvalidArgumentException(sprintf('Index "%s" columns must be list.', $indexName));
            }
            $indexes[$indexName] = new Index(
                $indexName,
                array_values(array_map(static fn (mixed $v): string => (string)$v, $cols)),
                (bool)($indexRaw['unique'] ?? false),
            );
        }

        $foreignKeys = [];
        foreach (($tableRaw['foreignKeys'] ?? []) as $fkName => $fkRaw) {
            if (!is_string($fkName) || !is_array($fkRaw)) {
                throw new \InvalidArgumentException(sprintf('Invalid foreign key in table "%s".', $tableName));
            }

            $refs = $fkRaw['references'] ?? null;
            if (!is_array($refs)) {
                throw new \InvalidArgumentException(sprintf('Foreign key "%s" references is required.', $fkName));
            }

            $columnsFk = $fkRaw['columns'] ?? [];
            $referenceColumns = $refs['columns'] ?? [];
            if (!is_array($columnsFk) || !is_array($referenceColumns)) {
                throw new \InvalidArgumentException(sprintf('Foreign key "%s" columns must be lists.', $fkName));
            }

            $foreignKeys[$fkName] = new ForeignKey(
                name: $fkName,
                columns: array_values(array_map(static fn (mixed $v): string => (string)$v, $columnsFk)),
                referenceTable: (string)($refs['table'] ?? ''),
                referenceColumns: array_values(array_map(static fn (mixed $v): string => (string)$v, $referenceColumns)),
                onDelete: isset($fkRaw['onDelete']) ? (string)$fkRaw['onDelete'] : null,
                onUpdate: isset($fkRaw['onUpdate']) ? (string)$fkRaw['onUpdate'] : null,
            );
        }

        return new Table(
            name: $tableName,
            columns: $columns,
            primaryKey: $primaryKey,
            indexes: $indexes,
            foreignKeys: $foreignKeys,
            engine: isset($tableRaw['engine']) ? (string)$tableRaw['engine'] : null,
            charset: isset($tableRaw['charset']) ? (string)$tableRaw['charset'] : null,
            collation: isset($tableRaw['collation']) ? (string)$tableRaw['collation'] : null,
        );
    }

    /**
     * @param array<string, mixed> $columnRaw
     */
    private function parseColumn(string $columnName, array $columnRaw): Column
    {
        $typeRaw = $columnRaw['type'] ?? null;
        if (!is_string($typeRaw)) {
            throw new \InvalidArgumentException(sprintf('Column "%s" must define string "type".', $columnName));
        }

        $type = ColumnType::tryFrom($typeRaw);
        if ($type === null) {
            throw new \InvalidArgumentException(sprintf('Unknown column type "%s" for column "%s".', $typeRaw, $columnName));
        }

        $enumValuesRaw = $columnRaw['values'] ?? [];
        if (!is_array($enumValuesRaw)) {
            throw new \InvalidArgumentException(sprintf('Column "%s" enum values must be list.', $columnName));
        }

        return new Column(
            name: $columnName,
            type: $type,
            nullable: (bool)($columnRaw['nullable'] ?? false),
            default: $columnRaw['default'] ?? null,
            primaryKey: (bool)($columnRaw['primaryKey'] ?? false),
            autoIncrement: (bool)($columnRaw['autoIncrement'] ?? false),
            length: isset($columnRaw['length']) ? (int)$columnRaw['length'] : null,
            precision: isset($columnRaw['precision']) ? (int)$columnRaw['precision'] : null,
            scale: isset($columnRaw['scale']) ? (int)$columnRaw['scale'] : null,
            unsigned: (bool)($columnRaw['unsigned'] ?? false),
            comment: isset($columnRaw['comment']) ? (string)$columnRaw['comment'] : null,
            enumValues: array_values(array_map(static fn (mixed $v): string => (string)$v, $enumValuesRaw)),
        );
    }
}
