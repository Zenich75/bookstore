<?php

namespace App\Lab\Queues;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The simplest job: records that it ran, where and when.
 */
class LabPingJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public string $variant, public array $details = []) {}

    public function handle(): void
    {
        JobRunLog::record($this->variant, $this, [...$this->details, 'batch' => $this->batchId]);
    }
}
