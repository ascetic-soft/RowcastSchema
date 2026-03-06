<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

final class MigrationLoader
{
    /**
     * @return array<string, string> [className => filePath]
     */
    public function load(string $migrationsPath): array
    {
        if (!is_dir($migrationsPath)) {
            return [];
        }

        $files = glob(rtrim($migrationsPath, '/\\') . '/Migration_*.php');
        if ($files === false) {
            return [];
        }

        sort($files, SORT_STRING);

        $result = [];
        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $result[$className] = $file;
        }

        return $result;
    }
}
