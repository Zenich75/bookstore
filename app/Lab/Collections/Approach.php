<?php

namespace App\Lab\Collections;

use App\Lab\ClosureSource;
use Closure;

/**
 * One side of a comparison: a label, the code that runs and an optional note.
 */
final readonly class Approach
{
    /**
     * @param  Closure(): mixed  $run
     */
    public function __construct(
        public string $label,
        public Closure $run,
        public ?string $note = null,
    ) {}

    public function code(): string
    {
        return ClosureSource::body($this->run);
    }
}
