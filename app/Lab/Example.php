<?php

namespace App\Lab;

use Closure;

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
        return ClosureSource::body($this->run);
    }
}
