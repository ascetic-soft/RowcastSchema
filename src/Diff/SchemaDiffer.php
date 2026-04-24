<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Diff;

use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
use AsceticSoft\RowcastSchema\Schema\Schema;

final readonly class SchemaDiffer
{
    public function __construct(
        private TableDiffer $tableDiffer = new TableDiffer(),
        private OperationOrderer $operationOrderer = new OperationOrderer(),
    ) {
    }

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

            array_push($operations, ...$this->tableDiffer->diff($fromTable, $toTable));
        }

        foreach ($from->tables as $tableName => $_) {
            if (!$to->hasTable($tableName)) {
                $operations[] = new DropTable($tableName);
            }
        }

        return $this->operationOrderer->order($operations, $from);
    }
}
