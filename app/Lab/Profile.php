<?php

namespace App\Lab;

/**
 * Metrics of one profiled run (see QueryProfiler).
 */
final readonly class Profile
{
    /**
     * @param  list<array{sql: string, count: int, ms: float}>  $groups  normalized SQL, most frequent first
     */
    public function __construct(
        public int $queries,
        public float $dbMs,
        public float $wallMs,
        public int $memoryBytes,
        public array $groups,
        public mixed $result = null,
        public ?string $error = null,
    ) {}

    /**
     * Groups that ran more than once — the N+1 suspects.
     *
     * @return list<array{sql: string, count: int, ms: float}>
     */
    public function duplicates(): array
    {
        return array_values(array_filter($this->groups, fn (array $group) => $group['count'] > 1));
    }
}
