<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

final class ClassScanner
{
    /**
     * @return list<string>
     */
    public function scan(string $path): array
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(\sprintf('Schema attribute path is not a directory: %s', $path));
        }

        $classes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            if (!\is_string($realPath) || $realPath === '') {
                continue;
            }

            $classes = [...$classes, ...$this->extractClassesFromFile($realPath)];
        }

        return array_values(array_unique($classes));
    }

    /**
     * @return list<string>
     */
    private function extractClassesFromFile(string $path): array
    {
        $source = file_get_contents($path);
        if (!\is_string($source) || $source === '') {
            return [];
        }

        $tokens = array_values(\PhpToken::tokenize($source));
        $namespace = '';
        $classes = [];

        foreach ($tokens as $index => $indexValue) {
            $token = $indexValue;

            if ($token->is(T_NAMESPACE)) {
                $namespace = $this->readNamespace($tokens, $index + 1);
                continue;
            }

            if (!$token->is(T_CLASS)) {
                continue;
            }

            $prevMeaningful = $this->previousMeaningfulToken($tokens, $index - 1);
            if ($prevMeaningful !== null && $prevMeaningful->is(T_NEW)) {
                continue;
            }

            $className = $this->readClassName($tokens, $index + 1);
            if ($className === null) {
                continue;
            }

            $classes[] = $namespace !== '' ? $namespace . '\\' . $className : $className;
        }

        return $classes;
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function readNamespace(array $tokens, int $index): string
    {
        $namespace = '';
        $count = \count($tokens);

        for (; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token->is([T_STRING, T_NAME_QUALIFIED])) {
                $namespace .= $token->text;
                continue;
            }

            if ($token->text === '\\') {
                $namespace .= '\\';
                continue;
            }

            if ($token->text === ';' || $token->text === '{') {
                break;
            }
        }

        return trim($namespace, '\\');
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function readClassName(array $tokens, int $index): ?string
    {
        $count = \count($tokens);

        for (; $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token->is(T_WHITESPACE)) {
                continue;
            }

            if ($token->is(T_STRING)) {
                return $token->text;
            }

            return null;
        }

        return null;
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function previousMeaningfulToken(array $tokens, int $index): ?\PhpToken
    {
        for (; $index >= 0; $index--) {
            $token = $tokens[$index];
            if ($token->is(T_WHITESPACE) || $token->is(T_COMMENT) || $token->is(T_DOC_COMMENT)) {
                continue;
            }

            return $token;
        }

        return null;
    }
}
