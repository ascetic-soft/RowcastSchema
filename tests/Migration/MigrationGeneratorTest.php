<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Migration;

use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\Index;
use PHPUnit\Framework\TestCase;

final class MigrationGeneratorTest extends TestCase
{
    public function testGeneratesUpAndDownForColumnOperations(): void
    {
        $dir = sys_get_temp_dir() . '/rowcast_gen_' . uniqid('', true);
        mkdir($dir, 0o777, true);

        $generator = new MigrationGenerator();
        $path = $generator->generate([
            new AddColumn('users', new Column('email', ColumnType::String, length: 255)),
            new AlterColumn(
                'users',
                new Column('name', ColumnType::String, length: 100),
                new Column('name', ColumnType::String, length: 150),
            ),
            new AddIndex(
                'users',
                new Index('idx_users_email', ['email'], true),
            ),
        ], $dir);

        $content = file_get_contents($path);
        self::assertIsString($content);
        self::assertStringContainsString("\$schema->addColumn('users', new Column(", $content);
        self::assertStringContainsString("\$schema->dropColumn('users', 'email');", $content);
        self::assertStringContainsString("\$schema->alterColumn('users', new Column(", $content);
        self::assertStringContainsString("\$schema->addIndex('users', 'idx_users_email', ['email'], true);", $content);
        self::assertStringNotContainsString('array (', $content);

        @unlink($path);
        @rmdir($dir);
    }
}
