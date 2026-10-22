<?php

namespace App\Lab\Queues;

use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The book is deleted between dispatch and execution. Restoring it throws
 * ModelNotFoundException — normally the job fails (and is retried, then lands in
 * failed_jobs). #[DeleteWhenMissingModels]: the job is silently discarded instead,
 * handle() never runs. Right for "send a mail about X" when X no longer exists.
 */
#[DeleteWhenMissingModels]
class MissingModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Book $book) {}

    public function handle(): void
    {
        JobRunLog::record('missing_model', $this, ['book' => $this->book->id]);
    }
}
