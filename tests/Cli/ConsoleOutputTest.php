<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Cli;

use AsceticSoft\RowcastSchema\Cli\ConsoleOutput;
use PHPUnit\Framework\TestCase;

final class ConsoleOutputTest extends TestCase
{
    public function testWritesPlainOutputWhenAnsiDisabled(): void
    {
        $output = new ConsoleOutput(noAnsi: true);
        self::assertFalse($output->isColorSupported());

        ob_start();
        try {
            $output->title('status');
            $output->success('done');
            $output->warning('warn');
            $output->info('info');
            $output->line('indented', 2);
            $output->newLine();
            $printed = (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertStringContainsString('Rowcast Schema -- status', $printed);
        self::assertStringContainsString('  [OK] done', $printed);
        self::assertStringContainsString('  [WARN] warn', $printed);
        self::assertStringContainsString('  [INFO] info', $printed);
        self::assertStringContainsString('  indented', $printed);
        self::assertStringNotContainsString("\033[", $printed);
    }

    public function testUsesAnsiWhenForceColorEnabled(): void
    {
        $output = new ConsoleOutput(noAnsi: false, forceColor: true);
        self::assertTrue($output->isColorSupported());

        ob_start();
        try {
            $output->success('done');
            $printed = (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertStringContainsString("\033[32m[OK]\033[0m", $printed);
    }

    public function testNoAnsiFlagOverridesForceColorAndErrorDoesNotThrow(): void
    {
        $output = new ConsoleOutput(noAnsi: true, forceColor: true);
        self::assertFalse($output->isColorSupported());

        $output->error('boom');
        self::assertTrue(true);
    }
}
