<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

final class ConfigFileLoader
{
    /**
     * @return array<mixed, mixed>
     */
    public function load(string $path, string $cwd): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Config file not found: %s', $path));
        }

        $config = require $path;
        if ($config instanceof \Closure) {
            $config = $config($cwd);
        }

        if (!\is_array($config)) {
            throw new \RuntimeException('Config file must return array or Closure returning array.');
        }

        return $config;
    }
}
