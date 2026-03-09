<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Migration;

use AsceticSoft\RowcastSchema\Diff\Operation\AddColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\AddForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\AddIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\AlterColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\CreateTable;
use AsceticSoft\RowcastSchema\Diff\Operation\DropColumn;
use AsceticSoft\RowcastSchema\Diff\Operation\DropForeignKey;
use AsceticSoft\RowcastSchema\Diff\Operation\DropIndex;
use AsceticSoft\RowcastSchema\Diff\Operation\DropTable;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use AsceticSoft\RowcastSchema\Schema\Column;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use AsceticSoft\RowcastSchema\Schema\ForeignKey;
use AsceticSoft\RowcastSchema\Schema\Index;
use AsceticSoft\RowcastSchema\Schema\Table;
use AsceticSoft\RowcastSchema\Diff\Operation\OperationInterface;
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
                'name',
                new Column('name', ColumnType::String, length: 150),
                new Column('name', ColumnType::String, length: 100),
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
        self::assertStringContainsString("\$schema->alterColumn('users', 'name', new Column(", $content);
        self::assertStringContainsString("\$schema->addIndex('users', 'idx_users_email', ['email'], true);", $content);
        self::assertStringNotContainsString('array (', $content);

        @unlink($path);
        @rmdir($dir);
    }

    public function testGeneratesAddColumnWithCustomDatabaseTypeWithoutTypeArgument(): void
    {
        $content = $this->generateAndRead([
            new AddColumn('ozon_categories', new Column(name: 'gigachat_embedding', databaseType: 'vector(1024)')),
            new AddColumn('ozon_categories', new Column(name: 'openai_embedding', databaseType: 'vector(1536)')),
        ]);

        self::assertStringContainsString(
            "\$schema->addColumn('ozon_categories', new Column(name: 'gigachat_embedding', databaseType: 'vector(1024)'));",
            $content,
        );
        self::assertStringContainsString(
            "\$schema->addColumn('ozon_categories', new Column(name: 'openai_embedding', databaseType: 'vector(1536)'));",
            $content,
        );
        self::assertStringNotContainsString(
            "new Column(name: 'gigachat_embedding', type: ColumnType::Text, databaseType: 'vector(1024)')",
            $content,
        );
        self::assertStringContainsString("\$schema->dropColumn('ozon_categories', 'gigachat_embedding');", $content);
        self::assertStringContainsString("\$schema->dropColumn('ozon_categories', 'openai_embedding');", $content);
    }

    public function testGeneratesCreateTableAndReverseDropTable(): void
    {
        $table = new Table(
            name: 'users',
            columns: [
                'id' => new Column('id', ColumnType::Integer, primaryKey: true, autoIncrement: true),
                'email' => new Column('email', ColumnType::String),
                'amount' => new Column('amount', ColumnType::Decimal, precision: 12, scale: 4),
            ],
            primaryKey: ['id'],
            indexes: ['idx_users_email' => new Index('idx_users_email', ['email'], true)],
            foreignKeys: ['fk_users_org' => new ForeignKey('fk_users_org', ['id'], 'organizations', ['id'], 'cascade', 'restrict')],
        );

        $content = $this->generateAndRead([new CreateTable($table)]);

        self::assertStringContainsString("\$schema->createTable('users', function (TableBuilder \$table): void {", $content);
        self::assertStringContainsString("\$table->column('id', ColumnType::Integer)->primaryKey()->autoIncrement();", $content);
        self::assertStringNotContainsString("\$table->primaryKey(['id']);", $content);
        self::assertStringContainsString("\$table->column('email', ColumnType::String)->length(255);", $content);
        self::assertStringContainsString("\$table->column('amount', ColumnType::Decimal)->precision(12, 4);", $content);
        self::assertStringContainsString("\$table->index('idx_users_email', ['email'], true);", $content);
        self::assertStringContainsString("\$table->foreignKey('fk_users_org', ['id'], 'organizations', ['id'], 'cascade', 'restrict');", $content);
        self::assertStringContainsString("\$schema->dropTable('users');", $content);
    }

    public function testGeneratesExplicitTablePrimaryKeyWhenDifferentFromColumnFlags(): void
    {
        $table = new Table(
            name: 'events',
            columns: [
                'tenant_id' => new Column('tenant_id', ColumnType::Uuid),
                'event_id' => new Column('event_id', ColumnType::Uuid),
                'payload' => new Column('payload', ColumnType::Json),
            ],
            primaryKey: ['tenant_id', 'event_id'],
        );

        $content = $this->generateAndRead([new CreateTable($table)]);

        self::assertStringContainsString(
            "\$table->primaryKey(['tenant_id', 'event_id']);",
            $content,
        );
    }

    public function testGeneratesForwardAndReverseForDropOperationsWithTodoComments(): void
    {
        $content = $this->generateAndRead([
            new DropTable('legacy_users'),
            new DropColumn('users', 'nickname'),
            new DropIndex('users', 'idx_users_legacy'),
            new DropForeignKey('users', 'fk_users_legacy'),
        ]);

        self::assertStringContainsString("\$schema->dropTable('legacy_users');", $content);
        self::assertStringContainsString('// TODO: recreate dropped table legacy_users manually for rollback.', $content);
        self::assertStringContainsString("\$schema->dropColumn('users', 'nickname');", $content);
        self::assertStringContainsString('// TODO: restore dropped column users.nickname manually for rollback.', $content);
        self::assertStringContainsString("\$schema->dropIndex('users', 'idx_users_legacy');", $content);
        self::assertStringContainsString('// TODO: restore dropped index idx_users_legacy on users manually for rollback.', $content);
        self::assertStringContainsString("\$schema->dropForeignKey('users', 'fk_users_legacy');", $content);
        self::assertStringContainsString('// TODO: restore dropped foreign key fk_users_legacy on users manually for rollback.', $content);
    }

    public function testGeneratesAlterColumnRollbackTodoWhenOldColumnIsMissing(): void
    {
        $content = $this->generateAndRead([
            new AlterColumn(
                'users',
                'name',
                new Column('name', ColumnType::String, length: 180),
                null,
            ),
        ]);

        self::assertStringContainsString("\$schema->alterColumn('users', 'name', new Column(", $content);
        self::assertStringContainsString(
            '// TODO: restore previous definition for altered column users.name manually for rollback.',
            $content,
        );
    }

    public function testGeneratesNoChangesCommentsForEmptyOperations(): void
    {
        $content = $this->generateAndRead([]);

        self::assertStringContainsString('// No schema changes detected.', $content);
        self::assertStringContainsString('// No rollback operations generated.', $content);
    }

    public function testGeneratesAddForeignKeyAndReverseDropForeignKey(): void
    {
        $content = $this->generateAndRead([
            new AddForeignKey('users', new ForeignKey('fk_users_org', ['org_id'], 'organizations', ['id'], 'set null', null)),
        ]);

        self::assertStringContainsString(
            "\$schema->addForeignKey('users', 'fk_users_org', ['org_id'], 'organizations', ['id'], 'set null', null);",
            $content,
        );
        self::assertStringContainsString("\$schema->dropForeignKey('users', 'fk_users_org');", $content);
    }

    /**
     * @param list<OperationInterface> $operations
     */
    private function generateAndRead(array $operations): string
    {
        $dir = sys_get_temp_dir() . '/rowcast_gen_' . uniqid('', true);
        mkdir($dir, 0o777, true);

        $path = new MigrationGenerator()->generate($operations, $dir);
        $content = file_get_contents($path);
        self::assertIsString($content);

        @unlink($path);
        @rmdir($dir);

        return $content;
    }
}
