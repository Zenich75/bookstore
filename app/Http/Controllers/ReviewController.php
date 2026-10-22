<?php

namespace App\Http\Controllers;

use App\Events\ReviewCreated;
use App\Events\ReviewDeleted;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * A reader's own review of a book. Moderation lives in Admin\ReviewController.
 * Routes: auth + verified + throttle:reviews (named limiter, AppServiceProvider).
 */
class ReviewController extends Controller
{
    /**
     * Target of the guest's "log in to write a review" link: the "auth" middleware
     * stores this URL as the intended one, so after login the reader lands on the form.
     */
    public function create(Book $book): RedirectResponse
    {
        return redirect()->to(route('books.show', $book).'#review-form');
    }

    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        try {
            // safe()->only(): Review's #[Fillable] includes is_approved (moderation sets it),
            // so create($request->all()) would let a reader approve their own review.
            $review = $book->reviews()->create([
                ...$request->safe()->only(['rating', 'body']),
                'user_id' => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Race: two submissions passed ReviewPolicy::create() at the same time; the
            // unique(user_id, book_id) index let only one INSERT through. Turn the DB
            // error into a normal validation error instead of a 500.
            throw ValidationException::withMessages(['body' => __('ui.reviews.already_reviewed')])
                ->redirectTo(route('books.show', $book).'#review-form');
        }

        // Listeners (app/Listeners) are queued: the reader does not wait for the
        // moderator e-mails. ShouldDispatchAfterCommit on the event: if this ran inside
        // a transaction that rolled back, no listener would run.
        ReviewCreated::dispatch($review);

        return redirect()->to(route('books.show', $book).'#review-form')
            ->with('status', __('ui.reviews.flash.created'));
    }

    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $review->update($request->validated());

        return $this->backToBook($review)->with('status', __('ui.reviews.flash.updated'));
    }

    /**
     * ReviewPolicy::delete: the author while pending (an admin rejects in /admin/reviews).
     */
    public function destroy(Review $review): RedirectResponse
    {
        Gate::authorize('delete', $review);

        $review->delete();
        ReviewDeleted::dispatch($review->id, $review->book_id, $review->user_id, $review->is_approved);

        return $this->backToBook($review)->with('status', __('ui.reviews.flash.deleted'));
    }

    private function backToBook(Review $review): RedirectResponse
    {
        $review->load('book:id,slug');

        return redirect()->to(route('books.show', $review->book).'#review-form');
    }
}
