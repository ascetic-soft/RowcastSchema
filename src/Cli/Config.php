<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

final readonly class Config
{
    /**
     * @param list<string|\Closure(string):bool> $ignoreTableRules
     */
    public function __construct(
        public string $schemaPath,
        public string $migrationsPath,
        public string $migrationTableName,
        public \PDO $pdo,
        public array $ignoreTableRules = [],
    ) {
    }

    public static function fromFile(string $path): self
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Unable to determine current working directory.');
        }

        $loader = new ConfigFileLoader();
        $normalizer = new ConfigNormalizer();
        $pdoFactory = new PdoFactory();

        $rawConfig = $loader->load($path, $cwd);
        $normalized = $normalizer->normalize($rawConfig, $cwd);

        return new self(
            schemaPath: $normalized['schemaPath'],
            migrationsPath: $normalized['migrationsPath'],
            migrationTableName: $normalized['migrationTableName'],
            pdo: $pdoFactory->create($normalized['connection']),
            ignoreTableRules: $normalized['ignoreTableRules'],
        );
    }
}
