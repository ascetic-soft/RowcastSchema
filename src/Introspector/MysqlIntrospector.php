<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\Schema;
use AsceticSoft\RowcastSchema\Schema\Table;
use AsceticSoft\RowcastSchema\TypeMapper\TypeMapperInterface;

final readonly class MysqlIntrospector implements IntrospectorInterface
{
    public function __construct(private TypeMapperInterface $typeMapper)
    {
    }

    public function introspect(\PDO $pdo): Schema
    {
        $dbStmt = $pdo->query('SELECT DATABASE()');
        if (!$dbStmt instanceof \PDOStatement) {
            throw new \RuntimeException('Unable to detect current MySQL database.');
        }
        $dbNameRaw = $dbStmt->fetchColumn();
        $dbName = \is_string($dbNameRaw) ? $dbNameRaw : '';
        if ($dbName === '') {
            throw new \RuntimeException('Unable to detect current MySQL database.');
        }

        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :schema
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
        );
        $stmt->execute(['schema' => $dbName]);

        $tables = [];
        /** @var array<string, mixed> $row */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $tableName = \is_string($row['TABLE_NAME'] ?? null) ? $row['TABLE_NAME'] : '';
            $columnName = \is_string($row['COLUMN_NAME'] ?? null) ? $row['COLUMN_NAME'] : '';
            $columnType = \is_string($row['COLUMN_TYPE'] ?? null) ? $row['COLUMN_TYPE'] : '';
            $isNullable = \is_string($row['IS_NULLABLE'] ?? null) ? $row['IS_NULLABLE'] : 'NO';
            $columnKey = \is_string($row['COLUMN_KEY'] ?? null) ? $row['COLUMN_KEY'] : '';
            $extra = \is_string($row['EXTRA'] ?? null) ? $row['EXTRA'] : '';
            if ($tableName === '' || $columnName === '' || $columnType === '') {
                continue;
            }
            $isPrimary = $columnKey === 'PRI';

            if (!isset($tables[$tableName])) {
                $tables[$tableName] = [
                    'columns' => [],
                    'pk' => [],
                ];
            }

            if ($isPrimary) {
                $tables[$tableName]['pk'][] = $columnName;
            }

            $tables[$tableName]['columns'][$columnName] = new Column(
                name: $columnName,
                type: $this->typeMapper->toAbstractType($columnType),
                nullable: $isNullable === 'YES',
                default: $row['COLUMN_DEFAULT'],
                primaryKey: $isPrimary,
                autoIncrement: str_contains($extra, 'auto_increment'),
            );
        }

        $result = [];
        foreach ($tables as $tableName => $data) {
            $result[$tableName] = new Table(
                name: $tableName,
                columns: $data['columns'],
                primaryKey: $data['pk'],
            );
        }

        return new Schema($result);
    }
}
