<?php

namespace App\Lab\Collections;

/**
 * "Same task, approach A vs B (vs C...)".
 */
final readonly class Comparison
{
    /**
     * @param  list<Approach>  $approaches
     * @param  bool  $writes  run each approach inside a transaction that is rolled back
     * @param  bool  $heavy  works on the whole table: measured on a single (cold) run
     */
    public function __construct(
        public string $section,
        public string $title,
        public string $description,
        public array $approaches,
        public bool $writes = false,
        public bool $heavy = false,
    ) {}
}
