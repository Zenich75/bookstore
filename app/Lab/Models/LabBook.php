<?php

namespace App\Lab\Models;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lab-only subclass of Book used by the N+1 lab (variants l and m).
 * Not used by the application itself.
 */
#[Table(name: 'books')]
class LabBook extends Book
{
    /**
     * Relations inherited from Book derive keys from the class name
     * (lab_book_id, author_lab_book). Point them back at the real ones.
     */
    public function getForeignKey(): string
    {
        return 'book_id';
    }

    public function joiningTableSegment(): string
    {
        return 'book';
    }

    /**
     * Use Book's morph alias (the morph map is enforced and has no LabBook entry).
     */
    public function getMorphClass(): string
    {
        return (new Book)->getMorphClass();
    }

    /**
     * Hidden N+1: looks like a plain attribute in Blade ({{ $book->author_names }}),
     * but reads the authors relation — one query per book unless eager-loaded.
     *
     * @return Attribute<string, never>
     */
    protected function authorNames(): Attribute
    {
        return Attribute::get(fn () => $this->authors
            ->map(fn ($author) => $author->first_name.' '.$author->last_name)
            ->join(', '));
    }

    /**
     * Plain hasMany: each $review->book is a separate query (inverse N+1).
     *
     * @return HasMany<Review, $this>
     */
    public function labReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'book_id');
    }

    /**
     * chaperone(): after loading, sets $review->book to this very model — no queries.
     *
     * @return HasMany<Review, $this>
     */
    public function labReviewsChaperoned(): HasMany
    {
        return $this->hasMany(Review::class, 'book_id')->chaperone('book');
    }
}
