<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The author deleted their own (pending) review. Ids only — see ReviewRejected.
 */
class ReviewDeleted implements ShouldDispatchAfterCommit
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
