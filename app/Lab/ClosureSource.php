<?php

namespace App\Lab;

use Closure;
use ReflectionFunction;

/**
 * Reads a closure's body from its source file, so lab pages show exactly the
 * code that runs. Expects the usual layout: `function (...) {` on the first
 * line and the closing `}` on the last line.
 */
final class ClosureSource
{
    public static function body(Closure $closure): string
    {
        $reflection = new ReflectionFunction($closure);
        $lines = array_slice(
            file($reflection->getFileName()),
            $reflection->getStartLine(),
            $reflection->getEndLine() - $reflection->getStartLine() - 1,
        );

        $indent = min(array_map(
            fn (string $line) => strlen($line) - strlen(ltrim($line)),
            array_filter($lines, fn (string $line) => trim($line) !== ''),
        ) ?: [0]);

        return rtrim(implode('', array_map(fn (string $line) => substr($line, $indent), $lines)));
    }
}
