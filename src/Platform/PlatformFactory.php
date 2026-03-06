<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Platform;

use AsceticSoft\RowcastSchema\TypeMapper\MysqlTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\PostgresTypeMapper;
use AsceticSoft\RowcastSchema\TypeMapper\SqliteTypeMapper;

final class PlatformFactory
{
    /**
     * @var array<string, callable(): PlatformInterface>
     */
    private array $registry;

    /**
     * @param array<string, callable(): PlatformInterface> $registry
     */
    public function __construct(array $registry = [])
    {
        $this->registry = $registry + [
            'mysql' => static fn (): PlatformInterface => new MysqlPlatform(new MysqlTypeMapper()),
            'pgsql' => static fn (): PlatformInterface => new PostgresPlatform(new PostgresTypeMapper()),
            'sqlite' => static fn (): PlatformInterface => new SqlitePlatform(new SqliteTypeMapper()),
        ];
    }

    public function createForPdo(\PDO $pdo): PlatformInterface
    {
        $driverRaw = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (!is_string($driverRaw) || $driverRaw === '') {
            throw new \RuntimeException('Unable to detect PDO driver name.');
        }
        $driver = $driverRaw;
        if (!isset($this->registry[$driver])) {
            throw new \RuntimeException(sprintf('Unsupported PDO driver "%s".', $driver));
        }

        return ($this->registry[$driver])();
    }
}
