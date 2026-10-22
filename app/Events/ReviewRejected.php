<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A moderator rejected (deleted) a review.
 *
 * Plain ids, not the Review model: the row is gone, so a queued listener could
 * not restore it (SerializesModels would throw ModelNotFoundException).
 */
class ReviewRejected implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $reviewId,
        public int $bookId,
        public int $userId,
        public bool $wasApproved,
    ) {}

    public function bookId(): int
    {
        return $this->bookId;
    }
}
