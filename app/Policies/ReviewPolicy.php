<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\Review;
use App\Models\Scopes\ApprovedScope;
use App\Models\User;

class ReviewPolicy
{
    /**
     * One review per user and book (the reviews table has a unique index too).
     * Pending reviews count: ApprovedScope would hide them, so it is bypassed —
     * otherwise a user could write a second review while the first awaits moderation.
     *
     * Excluded from the admin bypass in Gate::before: it is a data rule, not a privilege.
     */
    public function create(User $user, Book $book): bool
    {
        return $user->hasVerifiedEmail()
            && ! Review::withoutGlobalScope(ApprovedScope::class)
                ->where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->exists();
    }

    /**
     * Authors may edit their review while it awaits moderation.
     */
    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id && ! $review->is_approved;
    }

    /**
     * Admin: any review (reject = delete). Author: own pending review.
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->role === UserRole::Admin || $this->update($user, $review);
    }

    public function approve(User $user, Review $review): bool
    {
        return $user->role === UserRole::Admin && ! $review->is_approved;
    }
}
