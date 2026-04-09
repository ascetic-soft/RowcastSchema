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
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\ReferentialAction;
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

        return $this->sortOperations($operations, $from);
    }

    /**
     * @param list<OperationInterface> $operations
     * @return list<OperationInterface>
     */
    private function sortOperations(array $operations, Schema $from): array
    {
        $createOperations = [];
        $dropOperations = [];
        $otherOperations = [];

        foreach ($operations as $operation) {
            if ($operation instanceof CreateTable) {
                $createOperations[] = $operation;
                continue;
            }

            if ($operation instanceof DropTable) {
                $dropOperations[] = $operation;
                continue;
            }

            $otherOperations[] = $operation;
        }

        [$sortedCreateOperations, $extractedAddForeignKeys] = $this->topologicalSortCreateTables($createOperations);
        $sortedDropOperations = $this->topologicalSortDropTables($dropOperations, $from);

        return [
            ...$sortedCreateOperations,
            ...$extractedAddForeignKeys,
            ...$otherOperations,
            ...$sortedDropOperations,
        ];
    }

    /**
     * @param list<CreateTable> $createOperations
     * @return array{0: list<CreateTable>, 1: list<AddForeignKey>}
     */
    private function topologicalSortCreateTables(array $createOperations): array
    {
        if ($createOperations === []) {
            return [[], []];
        }

        $createByTable = [];
        $tableOrder = [];
        foreach ($createOperations as $operation) {
            $tableName = $operation->table->name;
            $createByTable[$tableName] = $operation;
            $tableOrder[] = $tableName;
        }

        [$sortedCreateOperations, $hasCycle, $cycleTables] = $this->stableTopologicalSortByDependencies(
            $createByTable,
            $tableOrder,
            $this->foreignKeyDependencyExtractor(...),
        );

        if (!$hasCycle) {
            return [$sortedCreateOperations, []];
        }

        $pendingTables = [];
        foreach ($cycleTables as $tableName) {
            $pendingTables[$tableName] = true;
        }

        $extractedAddForeignKeys = [];
        $reducedCreateOperations = [];

        foreach ($sortedCreateOperations as $operation) {
            $table = $operation->table;
            $remainingForeignKeys = [];
            foreach ($table->foreignKeys as $foreignKeyName => $foreignKey) {
                if (
                    $foreignKey->referenceTable !== $table->name
                    && isset($pendingTables[$table->name], $pendingTables[$foreignKey->referenceTable])
                ) {
                    $extractedAddForeignKeys[] = new AddForeignKey($table->name, $foreignKey);
                    continue;
                }

                $remainingForeignKeys[$foreignKeyName] = $foreignKey;
            }

            if ($remainingForeignKeys === $table->foreignKeys) {
                $reducedCreateOperations[] = $operation;
                continue;
            }

            $reducedCreateOperations[] = new CreateTable(new Table(
                name: $table->name,
                columns: $table->columns,
                primaryKey: $table->primaryKey,
                indexes: $table->indexes,
                foreignKeys: $remainingForeignKeys,
                engine: $table->engine,
                charset: $table->charset,
                collation: $table->collation,
            ));
        }

        $reducedCreateByTable = [];
        foreach ($reducedCreateOperations as $operation) {
            $reducedCreateByTable[$operation->table->name] = $operation;
        }

        [$resortedCreateOperations] = $this->stableTopologicalSortByDependencies(
            $reducedCreateByTable,
            $tableOrder,
            $this->foreignKeyDependencyExtractor(...),
        );

        return [$resortedCreateOperations, $extractedAddForeignKeys];
    }

    /**
     * @param list<DropTable> $dropOperations
     * @return list<DropTable>
     */
    private function topologicalSortDropTables(array $dropOperations, Schema $from): array
    {
        if ($dropOperations === []) {
            return [];
        }

        $dropByTable = [];
        $tableOrder = [];
        foreach ($dropOperations as $operation) {
            $dropByTable[$operation->tableName] = $operation;
            $tableOrder[] = $operation->tableName;
        }

        $referencedBy = [];
        foreach ($tableOrder as $tableName) {
            $referencedBy[$tableName] = [];
        }

        foreach ($tableOrder as $tableName) {
            $table = $from->getTable($tableName);
            if ($table === null) {
                continue;
            }

            foreach ($table->foreignKeys as $foreignKey) {
                if ($foreignKey->referenceTable === $tableName || !isset($dropByTable[$foreignKey->referenceTable])) {
                    continue;
                }

                $referencedBy[$foreignKey->referenceTable][$tableName] = true;
            }
        }

        [$sortedDropOperations] = $this->stableTopologicalSortByDependencies(
            $dropByTable,
            $tableOrder,
            static fn (DropTable $operation): array => array_keys($referencedBy[$operation->tableName] ?? []),
        );

        return $sortedDropOperations;
    }

    /**
     * @template TOperation of OperationInterface
     * @param array<string, TOperation> $operationsByTable
     * @param list<string> $tableOrder
     * @param callable(TOperation): list<string> $dependencyResolver
     * @return array{0: list<TOperation>, 1: bool, 2: list<string>}
     */
    private function stableTopologicalSortByDependencies(
        array $operationsByTable,
        array $tableOrder,
        callable $dependencyResolver,
    ): array {
        $dependents = [];
        $dependencyCount = [];
        foreach ($tableOrder as $tableName) {
            $dependents[$tableName] = [];
            $dependencyCount[$tableName] = 0;
        }

        foreach ($tableOrder as $tableName) {
            $operation = $operationsByTable[$tableName];
            foreach ($dependencyResolver($operation) as $dependency) {
                if (!isset($operationsByTable[$dependency])) {
                    continue;
                }

                $dependents[$dependency][$tableName] = true;
                ++$dependencyCount[$tableName];
            }
        }

        $queue = [];
        foreach ($tableOrder as $tableName) {
            if ($dependencyCount[$tableName] === 0) {
                $queue[] = $tableName;
            }
        }

        $sorted = [];
        $processed = [];

        while ($queue !== []) {
            /** @var string $tableName */
            $tableName = array_shift($queue);
            if (isset($processed[$tableName])) {
                continue;
            }

            $processed[$tableName] = true;
            $sorted[] = $operationsByTable[$tableName];

            foreach (array_keys($dependents[$tableName]) as $dependentTable) {
                --$dependencyCount[$dependentTable];
                if ($dependencyCount[$dependentTable] === 0) {
                    $queue[] = $dependentTable;
                }
            }
        }

        $hasCycle = \count($sorted) !== \count($tableOrder);
        if (!$hasCycle) {
            return [$sorted, false, []];
        }

        $pendingTables = [];
        foreach ($tableOrder as $tableName) {
            if (isset($processed[$tableName])) {
                continue;
            }

            $pendingTables[] = $tableName;
            $sorted[] = $operationsByTable[$tableName];
        }

        return [$sorted, true, $pendingTables];
    }

    /**
     * @return list<string>
     */
    private function foreignKeyDependencyExtractor(CreateTable $operation): array
    {
        $dependencies = [];
        $tableName = $operation->table->name;
        foreach ($operation->table->foreignKeys as $foreignKey) {
            if ($foreignKey->referenceTable !== $tableName) {
                $dependencies[$foreignKey->referenceTable] = true;
            }
        }

        return array_keys($dependencies);
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
                $operations[] = new AlterColumn($to->name, $oldColumn->name, $newColumn, $oldColumn);
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

            if (!$this->foreignKeysSemanticallyEqual($oldFk, $newFk)) {
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

    private function foreignKeysSemanticallyEqual(ForeignKey $a, ForeignKey $b): bool
    {
        return $a->name === $b->name
            && $a->columns === $b->columns
            && $a->referenceTable === $b->referenceTable
            && $a->referenceColumns === $b->referenceColumns
            && $this->normalizeReferentialActionForCompare($a->onDelete) === $this->normalizeReferentialActionForCompare($b->onDelete)
            && $this->normalizeReferentialActionForCompare($a->onUpdate) === $this->normalizeReferentialActionForCompare($b->onUpdate);
    }

    /**
     * Align introspection (NO ACTION → null) with schema files (explicit NO ACTION → enum)
     * and enum vs equivalent string literals from migrations.
     *
     * @return ReferentialAction|string|null
     */
    private function normalizeReferentialActionForCompare(ReferentialAction|string|null $value): ReferentialAction|string|null
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
