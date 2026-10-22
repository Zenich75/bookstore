<?php

namespace App\Events;

use App\Models\Review;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A moderator approved a review: the book's rating changes.
 */
class ReviewApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Review $review) {}

    public function bookId(): int
    {
        return $this->review->book_id;
    }
}
