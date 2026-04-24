<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

final class MigrationInstantiator
{
    public function instantiate(string $version, string $filePath): MigrationInterface
    {
        if (!class_exists($version, false)) {
            require_once $filePath;
        }

        if (!class_exists($version)) {
            throw new \RuntimeException(\sprintf('Migration class "%s" was not found in %s.', $version, $filePath));
        }

        $migration = new $version();
        if (!$migration instanceof MigrationInterface) {
            throw new \RuntimeException(\sprintf('Migration "%s" must implement MigrationInterface.', $version));
        }

        return $migration;
    }
}
