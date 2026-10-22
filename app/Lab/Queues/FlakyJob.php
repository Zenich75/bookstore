<?php

namespace App\Lab\Queues;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use RuntimeException;
use Throwable;

/**
 * Fails at random (70 %). Each attempt is logged; after the 4th failed attempt
 * the job lands in failed_jobs (queue:failed, queue:retry <id>, queue:flush).
 * Backoff 2 s, 5 s, 10 s: the wait before the 2nd, 3rd and 4th attempt.
 */
#[Tries(4)]
#[Backoff(2, 5, 10)]
class FlakyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $failurePercent = 70) {}

    public function handle(): void
    {
        $fails = random_int(1, 100) <= $this->failurePercent;
        JobRunLog::record('flaky', $this, ['outcome' => $fails ? 'exception' : 'ok']);

        if ($fails) {
            throw new RuntimeException('FlakyJob: random failure on attempt '.$this->attempts());
        }
    }

    public function failed(?Throwable $exception): void
    {
        JobRunLog::record('flaky', $this, ['outcome' => 'failed() — moved to failed_jobs']);
    }
}
