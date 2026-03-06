<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\TypeMapper\MysqlTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\PostgresTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;

final class IntrospectorFactory
{
    /**
     * @var array<string, callable(): IntrospectorInterface>
     */
    private array $registry;

    /**
     * @param array<string, callable(): IntrospectorInterface> $registry
     */
    public function __construct(array $registry = [])
    {
        $this->registry = $registry + [
            'mysql' => static fn (): IntrospectorInterface => new MysqlIntrospector(new MysqlTypeMapper()),
            'pgsql' => static fn (): IntrospectorInterface => new PostgresIntrospector(new PostgresTypeMapper()),
            'sqlite' => static fn (): IntrospectorInterface => new SqliteIntrospector(new SqliteTypeMapper()),
        ];
    }

    public function createForPdo(\PDO $pdo): IntrospectorInterface
    {
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (!isset($this->registry[$driver])) {
            throw new \RuntimeException(sprintf('Unsupported PDO driver "%s".', $driver));
        }

        return ($this->registry[$driver])();
    }
}
