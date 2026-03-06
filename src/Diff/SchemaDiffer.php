<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff;

use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;

final class SchemaDiffer
{
    /**
     * @return list<OperationInterface>
     */
    public function diff(Schema $from, Schema $to): array
    {
        $operations = [];

        foreach ($to->tables as $tableName => $toTable) {
            $fromTable = $from->getTable($tableName);
            if ($fromTable === null) {
                $operations[] = new CreateTable($toTable);
                continue;
            }

            array_push($operations, ...$this->diffTable($fromTable, $toTable));
        }

        foreach ($from->tables as $tableName => $_) {
            if (!$to->hasTable($tableName)) {
                $operations[] = new DropTable($tableName);
            }
        }

        return $operations;
    }

    /**
     * @return list<OperationInterface>
     */
    private function diffTable(Table $from, Table $to): array
    {
        $operations = [];

        foreach ($to->columns as $columnName => $newColumn) {
            $oldColumn = $from->getColumn($columnName);
            if ($oldColumn === null) {
                $operations[] = new AddColumn($to->name, $newColumn);
                continue;
            }

            if ($oldColumn != $newColumn) {
                $operations[] = new AlterColumn($to->name, $oldColumn, $newColumn);
            }
        }

        foreach ($from->columns as $columnName => $_) {
            if (!$to->hasColumn($columnName)) {
                $operations[] = new DropColumn($to->name, $columnName);
            }
        }

        foreach ($to->indexes as $indexName => $newIndex) {
            $oldIndex = $from->indexes[$indexName] ?? null;
            if ($oldIndex === null) {
                $operations[] = new AddIndex($to->name, $newIndex);
                continue;
            }

            if ($oldIndex != $newIndex) {
                $operations[] = new DropIndex($to->name, $indexName);
                $operations[] = new AddIndex($to->name, $newIndex);
            }
        }

        foreach ($from->indexes as $indexName => $_) {
            if (!isset($to->indexes[$indexName])) {
                $operations[] = new DropIndex($to->name, $indexName);
            }
        }

        foreach ($to->foreignKeys as $fkName => $newFk) {
            $oldFk = $from->foreignKeys[$fkName] ?? null;
            if ($oldFk === null) {
                $operations[] = new AddForeignKey($to->name, $newFk);
                continue;
            }

            if ($oldFk != $newFk) {
                $operations[] = new DropForeignKey($to->name, $fkName, $oldFk);
                $operations[] = new AddForeignKey($to->name, $newFk);
            }
        }

        foreach ($from->foreignKeys as $fkName => $_) {
            if (!isset($to->foreignKeys[$fkName])) {
                $operations[] = new DropForeignKey($to->name, $fkName, $from->foreignKeys[$fkName]);
            }
        }

        return $operations;
    }
}
