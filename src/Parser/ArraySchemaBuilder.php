<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;

final class ArraySchemaBuilder
{
    /**
     * @param array<mixed, mixed> $parsed
     */
    public function build(array $parsed): Schema
    {
        $tablesRaw = $this->requireMap($parsed['tables'] ?? null, 'Schema must contain "tables" mapping.');

        $tables = [];
        foreach ($tablesRaw as $tableName => $tableRaw) {
            $tables[$tableName] = $this->parseTable(
                $tableName,
                $this->requireMap($tableRaw, 'Invalid table definition.'),
            );
        }

        return new Schema($tables);
    }

    /**
     * @param array<string, mixed> $tableRaw
     */
    private function parseTable(string $tableName, array $tableRaw): Table
    {
        $columnsRaw = $this->requireMap(
            $tableRaw['columns'] ?? null,
            sprintf('Table "%s" must define non-empty "columns".', $tableName),
        );
        if ($columnsRaw === []) {
            throw new \InvalidArgumentException(sprintf('Table "%s" must define non-empty "columns".', $tableName));
        }

        $columns = [];
        $autoPrimary = [];
        foreach ($columnsRaw as $columnName => $columnRaw) {
            $column = $this->parseColumn(
                $columnName,
                $this->requireMap($columnRaw, sprintf('Invalid column in table "%s".', $tableName)),
            );
            $columns[$columnName] = $column;
            if ($column->primaryKey) {
                $autoPrimary[] = $columnName;
            }
        }

        $primaryKey = $autoPrimary;
        if (isset($tableRaw['primaryKey'])) {
            $primaryKey = $this->toStringList(
                $tableRaw['primaryKey'],
                sprintf('Table "%s" primaryKey must be list.', $tableName),
            );
        }

        $indexesRaw = $tableRaw['indexes'] ?? [];
        if (!is_array($indexesRaw)) {
            throw new \InvalidArgumentException(sprintf('Table "%s" indexes must be mapping.', $tableName));
        }
        $indexes = [];
        foreach ($indexesRaw as $indexName => $indexRaw) {
            $indexMap = $this->requireMap($indexRaw, sprintf('Invalid index in table "%s".', $tableName));
            $cols = $this->toStringList(
                $indexMap['columns'] ?? [],
                sprintf('Index "%s" columns must be list.', $indexName),
            );
            $indexes[$indexName] = new Index(
                $indexName,
                $cols,
                (bool)($indexMap['unique'] ?? false),
            );
        }

        $foreignKeysRaw = $tableRaw['foreignKeys'] ?? [];
        if (!is_array($foreignKeysRaw)) {
            throw new \InvalidArgumentException(sprintf('Table "%s" foreignKeys must be mapping.', $tableName));
        }
        $foreignKeys = [];
        foreach ($foreignKeysRaw as $fkName => $fkRaw) {
            $fkMap = $this->requireMap($fkRaw, sprintf('Invalid foreign key in table "%s".', $tableName));
            $refs = $this->requireMap(
                $fkMap['references'] ?? null,
                sprintf('Foreign key "%s" references is required.', $fkName),
            );
            $columnsFk = $this->toStringList(
                $fkMap['columns'] ?? [],
                sprintf('Foreign key "%s" columns must be lists.', $fkName),
            );
            $referenceColumns = $this->toStringList(
                $refs['columns'] ?? [],
                sprintf('Foreign key "%s" columns must be lists.', $fkName),
            );
            $referenceTable = $this->toString($refs['table'] ?? '', 'Foreign key reference table must be string.');

            $foreignKeys[$fkName] = new ForeignKey(
                name: $fkName,
                columns: $columnsFk,
                referenceTable: $referenceTable,
                referenceColumns: $referenceColumns,
                onDelete: isset($fkMap['onDelete']) ? $this->toString($fkMap['onDelete'], 'Foreign key onDelete must be string.') : null,
                onUpdate: isset($fkMap['onUpdate']) ? $this->toString($fkMap['onUpdate'], 'Foreign key onUpdate must be string.') : null,
            );
        }

        return new Table(
            name: $tableName,
            columns: $columns,
            primaryKey: $primaryKey,
            indexes: $indexes,
            foreignKeys: $foreignKeys,
            engine: isset($tableRaw['engine']) ? $this->toString($tableRaw['engine'], 'Table engine must be string.') : null,
            charset: isset($tableRaw['charset']) ? $this->toString($tableRaw['charset'], 'Table charset must be string.') : null,
            collation: isset($tableRaw['collation']) ? $this->toString($tableRaw['collation'], 'Table collation must be string.') : null,
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

        $length = isset($columnRaw['length']) ? $this->toInt($columnRaw['length'], 'Column length must be integer.') : null;
        $precision = isset($columnRaw['precision']) ? $this->toInt($columnRaw['precision'], 'Column precision must be integer.') : null;
        $scale = isset($columnRaw['scale']) ? $this->toInt($columnRaw['scale'], 'Column scale must be integer.') : null;

        return new Column(
            name: $columnName,
            type: $type,
            nullable: (bool)($columnRaw['nullable'] ?? false),
            default: $columnRaw['default'] ?? null,
            primaryKey: (bool)($columnRaw['primaryKey'] ?? false),
            autoIncrement: (bool)($columnRaw['autoIncrement'] ?? false),
            length: $length,
            precision: $precision,
            scale: $scale,
            unsigned: (bool)($columnRaw['unsigned'] ?? false),
            comment: isset($columnRaw['comment']) ? $this->toString($columnRaw['comment'], 'Column comment must be string.') : null,
            enumValues: $this->toStringList($enumValuesRaw, sprintf('Column "%s" enum values must be list.', $columnName)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requireMap(mixed $value, string $message): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($message);
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException($message);
            }
            $map[$key] = $item;
        }

        return $map;
    }

    private function toString(mixed $value, string $message): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }

    private function toInt(mixed $value, string $message): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int)$value;
        }

        throw new \InvalidArgumentException($message);
    }

    /**
     * @return list<string>
     */
    private function toStringList(mixed $value, string $message): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($message);
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new \InvalidArgumentException($message);
            }
            $result[] = $item;
        }

        return $result;
    }
}
