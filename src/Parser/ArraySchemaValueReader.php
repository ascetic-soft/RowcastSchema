<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Parser;

final class ArraySchemaValueReader
{
    /**
     * @return array<string, mixed>
     */
    public function requireMap(mixed $value, string $message): array
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException($message);
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException($message);
            }
            $map[$key] = $item;
        }

        return $map;
    }

    public function toString(mixed $value, string $message): string
    {
        if (!\is_string($value)) {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }

    public function toInt(mixed $value, string $message): int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException($message);
    }

    /**
     * @return list<string>
     */
    public function toStringList(mixed $value, string $message): array
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException($message);
        }

        $result = [];
        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw new \InvalidArgumentException($message);
            }
            $result[] = $item;
        }

        return $result;
    }
}
