<?php

namespace App\Events;

use App\Models\Review;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A reader submitted a review (pending moderation).
 *
 * ShouldDispatchAfterCommit: fired inside a transaction, listeners run only after
 * COMMIT — and not at all on ROLLBACK — so nobody acts on a review that never existed.
 *
 * SerializesModels: queued listeners get the review re-fetched by id on the worker.
 * Restoration ignores global scopes (Model::newQueryForRestoration()), so a pending
 * review is found despite ApprovedScope.
 */
class ReviewCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Review $review) {}
}
