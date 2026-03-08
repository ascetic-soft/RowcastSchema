<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Pdo\PdoDriverResolver;
use AsceticSoft\RowcastSchema\TypeMapper\MysqlTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\PostgresTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;

final class IntrospectorFactory
{
    /**
     * @var array<string, callable(): IntrospectorInterface>
     */
    private array $registry;
    private PdoDriverResolver $driverResolver;

    /**
     * @param array<string, callable(): IntrospectorInterface> $registry
     */
    public function __construct(array $registry = [], ?PdoDriverResolver $driverResolver = null)
    {
        $this->registry = $registry + [
            'mysql' => static fn (): IntrospectorInterface => new MysqlIntrospector(new MysqlTypeMapper()),
            'pgsql' => static fn (): IntrospectorInterface => new PostgresIntrospector(new PostgresTypeMapper()),
            'sqlite' => static fn (): IntrospectorInterface => new SqliteIntrospector(new SqliteTypeMapper()),
        ];
        $this->driverResolver = $driverResolver ?? new PdoDriverResolver();
    }

    public function createForPdo(\PDO $pdo): IntrospectorInterface
    {
        $driver = $this->driverResolver->resolve($pdo);
        if (!isset($this->registry[$driver])) {
            throw new \RuntimeException(\sprintf('Unsupported PDO driver "%s".', $driver));
        }

        return ($this->registry[$driver])();
    }
}
