<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Platform;

use AsceticSoft\RowcastSchema\Pdo\PdoDriverResolver;
use AsceticSoft\RowcastSchema\TypeMapper\MysqlTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\PostgresTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;

final class PlatformFactory
{
    /**
     * @var array<string, callable(): PlatformInterface>
     */
    private array $registry;
    private PdoDriverResolver $driverResolver;

    /**
     * @param array<string, callable(): PlatformInterface> $registry
     */
    public function __construct(array $registry = [], ?PdoDriverResolver $driverResolver = null)
    {
        $this->registry = $registry + [
            'mysql' => static fn (): PlatformInterface => new MysqlPlatform(new MysqlTypeMapper()),
            'pgsql' => static fn (): PlatformInterface => new PostgresPlatform(new PostgresTypeMapper()),
            'sqlite' => static fn (): PlatformInterface => new SqlitePlatform(new SqliteTypeMapper()),
        ];
        $this->driverResolver = $driverResolver ?? new PdoDriverResolver();
    }

    public function createForPdo(\PDO $pdo): PlatformInterface
    {
        $driver = $this->driverResolver->resolve($pdo);
        if (!isset($this->registry[$driver])) {
            throw new \RuntimeException(\sprintf('Unsupported PDO driver "%s".', $driver));
        }

        return ($this->registry[$driver])();
    }
}
