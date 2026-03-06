<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Pdo;

final class PdoDriverResolver
{
    public function resolve(\PDO $pdo): string
    {
        $driverRaw = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (!\is_string($driverRaw) || $driverRaw === '') {
            throw new \RuntimeException('Unable to detect PDO driver name.');
        }

        return $driverRaw;
    }
}
