<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * books.rating_avg = average of the book's approved reviews (denormalized for the
 * catalog's "by rating" sort — sorting 50k books by a live AVG() subquery is slow).
 *
 * ShouldBeUniqueUntilProcessing, not ShouldBeUnique: the lock (per uniqueId = book)
 * is released when a worker *starts* the job. ShouldBeUnique would hold it until
 * the job finishes, so a review approved while the job is already running — after
 * its AVG() was read — would be dropped as a "duplicate", and the stale average
 * would stay. With UntilProcessing that approval queues a fresh run.
 * Ten approvals in a row before any worker picks the job up still mean one run.
 */
class RecalculateBookRating implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookId) {}

    public function uniqueId(): string
    {
        return (string) $this->bookId;
    }

    public function handle(): void
    {
        self::forBooks([$this->bookId]);
    }

    /**
     * One set-based UPDATE with a correlated subquery: the average is computed in SQL,
     * no rows are loaded into PHP, and no model events fire (query-level update).
     *
     * @param  list<int>  $bookIds
     */
    public static function forBooks(array $bookIds): int
    {
        if ($bookIds === []) {
            return 0;
        }

        return DB::table('books')->whereIn('id', $bookIds)->update([
            'rating_avg' => DB::raw('(select round(avg(reviews.rating), 2) from reviews
                where reviews.book_id = books.id and reviews.is_approved = 1)'),
        ]);
    }
}
