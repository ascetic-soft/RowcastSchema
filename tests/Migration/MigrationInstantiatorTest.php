<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Migration;

use AsceticSoft\RowcastSchema\Migration\MigrationInstantiator;
use AsceticSoft\RowcastSchema\Migration\MigrationInterface;
use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;
use PHPUnit\Framework\TestCase;

final class MigrationInstantiatorTest extends TestCase
{
    public function testInstantiatesMigrationClassFromFile(): void
    {
        $file = $this->writeMigrationFile(<<<'PHP'
            <?php
            declare(strict_types=1);

            use AsceticSoft\RowcastSchema\Migration\MigrationInterface;
            use AsceticSoft\RowcastSchema\SchemaBuilder\SchemaBuilder;

            final class Migration_Test_Instantiator_Success implements MigrationInterface
            {
                public function up(SchemaBuilder $schema): void {}
                public function down(SchemaBuilder $schema): void {}
            }
            PHP);

        try {
            $instantiator = new MigrationInstantiator();
            $migration = $instantiator->instantiate('Migration_Test_Instantiator_Success', $file);

            self::assertInstanceOf(MigrationInterface::class, $migration);
        } finally {
            @unlink($file);
        }
    }

    public function testThrowsWhenClassIsMissing(): void
    {
        $file = $this->writeMigrationFile('<?php declare(strict_types=1);');

        try {
            $instantiator = new MigrationInstantiator();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Migration class "Migration_Test_Instantiator_Missing" was not found');
            $instantiator->instantiate('Migration_Test_Instantiator_Missing', $file);
        } finally {
            @unlink($file);
        }
    }

    public function testThrowsWhenClassDoesNotImplementMigrationInterface(): void
    {
        $file = $this->writeMigrationFile(<<<'PHP'
            <?php
            declare(strict_types=1);

            final class Migration_Test_Instantiator_Invalid
            {
            }
            PHP);

        try {
            $instantiator = new MigrationInstantiator();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Migration "Migration_Test_Instantiator_Invalid" must implement MigrationInterface.');
            $instantiator->instantiate('Migration_Test_Instantiator_Invalid', $file);
        } finally {
            @unlink($file);
        }
    }

    private function writeMigrationFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/rowcast_migration_instantiator_' . uniqid('', true) . '.php';
        file_put_contents($path, $content);

        return $path;
    }
}
