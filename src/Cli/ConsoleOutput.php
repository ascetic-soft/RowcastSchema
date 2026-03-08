<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Cli;

final readonly class ConsoleOutput
{
    private bool $colorSupported;

    public function __construct(bool $noAnsi = false, ?bool $forceColor = null)
    {
        $this->colorSupported = !$noAnsi && ($forceColor ?? $this->detectColorSupport());
    }

    public function title(string $command): void
    {
        $this->writeLine($this->style('Rowcast Schema -- ' . $command, '1;36'));
    }

    public function success(string $message): void
    {
        $this->writeLine('  ' . $this->style('[OK]', '32') . ' ' . $message);
    }

    public function warning(string $message): void
    {
        $this->writeLine('  ' . $this->style('[WARN]', '33') . ' ' . $message);
    }

    public function info(string $message): void
    {
        $this->writeLine('  ' . $this->style('[INFO]', '36') . ' ' . $message);
    }

    public function error(string $message): void
    {
        $this->writeLine('[rowcast-schema] ' . $this->style($message, '31'), STDERR);
    }

    public function line(string $message, int $indent = 0): void
    {
        $indentation = str_repeat(' ', max(0, $indent));
        $this->writeLine($indentation . $message);
    }

    public function newLine(): void
    {
        $this->writeLine('');
    }

    public function isColorSupported(): bool
    {
        return $this->colorSupported;
    }

    private function style(string $message, string $code): string
    {
        if (!$this->colorSupported) {
            return $message;
        }

        return "\033[{$code}m$message\033[0m";
    }

    /**
     * @param resource $stream
     */
    private function writeLine(string $message, $stream = STDOUT): void
    {
        if ($stream === STDOUT) {
            echo $message . PHP_EOL;
            return;
        }

        fwrite($stream, $message . PHP_EOL);
    }

    private function detectColorSupport(): bool
    {
        if ($this->isNoColorEnabled()) {
            return false;
        }

        if (\DIRECTORY_SEPARATOR === '\\') {
            return true;
        }

        if (\function_exists('stream_isatty')) {
            return @stream_isatty(STDOUT);
        }

        if (\function_exists('posix_isatty')) {
            return @posix_isatty(STDOUT);
        }

        return false;
    }

    private function isNoColorEnabled(): bool
    {
        $value = getenv('NO_COLOR');

        return \is_string($value) && $value !== '';
    }
}
