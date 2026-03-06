<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use AsceticSoft\RowcastSchema\TypeMapper\TypeMapperInterface;

final readonly class SqliteIntrospector implements IntrospectorInterface
{
    public function __construct(private TypeMapperInterface $typeMapper)
    {
    }

    public function introspect(\PDO $pdo): Schema
    {
        $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        if ($tablesStmt === false) {
            throw new \RuntimeException('Failed to introspect sqlite tables.');
        }

        $tables = [];
        /** @var array{name: string} $row */
        foreach ($tablesStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $tableName = (string)$row['name'];
            $columns = [];
            $pk = [];

            $colsStmt = $pdo->query(sprintf("PRAGMA table_info('%s')", str_replace("'", "''", $tableName)));
            if ($colsStmt === false) {
                throw new \RuntimeException(sprintf('Failed to introspect sqlite columns for %s.', $tableName));
            }

            /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $col */
            foreach ($colsStmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
                $name = (string)$col['name'];
                $isPrimary = (int)$col['pk'] > 0;
                if ($isPrimary) {
                    $pk[] = $name;
                }

                $columns[$name] = new Column(
                    name: $name,
                    type: $this->typeMapper->toAbstractType((string)$col['type']),
                    nullable: (int)$col['notnull'] === 0,
                    default: $col['dflt_value'],
                    primaryKey: $isPrimary,
                    autoIncrement: false,
                );
            }

            $tables[$tableName] = new Table(
                name: $tableName,
                columns: $columns,
                primaryKey: $pk,
            );
        }

        return new Schema($tables);
    }
}
