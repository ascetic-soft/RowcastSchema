<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

use AsceticSoft\RowcastSchema\Platform\PlatformInterface;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

final readonly class OperationExecutor
{
    public function __construct(
        private \PDO $pdo,
        private PlatformInterface $platform,
        private ?SqliteTableRebuilder $sqliteTableRebuilder = null,
    ) {
    }

    public function execute(SchemaBuilder $builder): void
    {
        $executor = function () use ($builder): void {
            foreach ($builder->getOperations() as $operation) {
                if ($this->sqliteTableRebuilder !== null && $this->sqliteTableRebuilder->supports($operation)) {
                    $this->sqliteTableRebuilder->execute($this->pdo, $operation);
                    continue;
                }

                foreach ($this->platform->toSql($operation) as $sql) {
                    $this->pdo->exec($sql);
                }
            }
        };

        if ($this->platform->supportsDdlTransactions()) {
            $this->pdo->beginTransaction();
            try {
                $executor();
                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }

            return;
        }

        $executor();
    }
}
