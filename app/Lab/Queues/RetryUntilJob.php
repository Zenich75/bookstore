<?php

namespace App\Lab\Queues;

use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use RuntimeException;

/**
 * Time-based instead of count-based retries: retryUntil() overrides $tries — the
 * job is retried (every 5 s) as long as the deadline has not passed. Typical for
 * "the remote API is down, keep trying for 30 seconds". Succeeds on attempt 3.
 */
#[Backoff(5)]
class RetryUntilJob implements ShouldQueue
{
    use Queueable;

    public function retryUntil(): DateTimeInterface
    {
        return now()->addSeconds(30);
    }

    public function handle(): void
    {
        $succeeds = $this->attempts() >= 3;
        JobRunLog::record('retry_until', $this, ['outcome' => $succeeds ? 'ok' : 'exception, retry in 5 s']);

        if (! $succeeds) {
            throw new RuntimeException('Not yet (attempt '.$this->attempts().')');
        }
    }
}
