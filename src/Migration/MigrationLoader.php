<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Migration;

final readonly class MigrationLoader
{
    /**
     * @param null|callable(string): (array<int, string>|false) $fileLister
     */
    public function __construct(
        private mixed $fileLister = null,
    ) {
    }

    /**
     * @return array<string, string> [className => filePath]
     */
    public function load(string $migrationsPath): array
    {
        if (!is_dir($migrationsPath)) {
            return [];
        }

        $files = $this->listFiles(rtrim($migrationsPath, '/\\') . '/Migration_*.php');
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

    /**
     * @return array<int, string>|false
     */
    private function listFiles(string $pattern): array|false
    {
        if (is_callable($this->fileLister)) {
            return ($this->fileLister)($pattern);
        }

        return glob($pattern);
    }
}
