<?php

namespace App\Lab;

use Closure;
use ReflectionFunction;

/**
 * One runnable lab example. The displayed code is read from the closure's own
 * source, so what you see is exactly what runs.
 */
final readonly class Example
{
    /**
     * @param  Closure(Samples): mixed  $run
     */
    public function __construct(
        public string $section,
        public string $title,
        public string $type,
        public Closure $run,
        public bool $writes = false,
    ) {}

    /**
     * Body of the closure, without the `function (Samples $s) {` / `}` lines.
     */
    public function code(): string
    {
        $reflection = new ReflectionFunction($this->run);
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
