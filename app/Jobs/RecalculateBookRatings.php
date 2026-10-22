<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A chunk of books for books:recalculate-ratings (one job per 1000 ids, all in a batch).
 * Not unique: the full recalculation is an explicit admin action.
 */
class RecalculateBookRatings implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * @param  list<int>  $bookIds
     */
    public function __construct(public array $bookIds) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        RecalculateBookRating::forBooks($this->bookIds);
    }
}
