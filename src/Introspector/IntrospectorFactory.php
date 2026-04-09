<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Pdo\PdoDriverResolver;
use AsceticSoft\RowcastSchema\TypeMapper\MysqlTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\PostgresTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;

final readonly class IntrospectorFactory
{
    public function __construct(private PdoDriverResolver $driverResolver = new PdoDriverResolver())
    {
    }

    public function createForPdo(\PDO $pdo): IntrospectorInterface
    {
        $driver = $this->driverResolver->resolve($pdo);

        return match ($driver) {
            'mysql' => new MysqlIntrospector(new MysqlTypeMapper()),
            'pgsql' => new PostgresIntrospector(new PostgresTypeMapper()),
            'sqlite' => new SqliteIntrospector(new SqliteTypeMapper()),
            default => throw new \RuntimeException(\sprintf('Unsupported PDO driver "%s".', $driver)),
        };
    }
}
