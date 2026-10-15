<?php

namespace App\Lab\Models;

use App\Models\Book;
use App\Models\Review;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Lab-only subclass of Book used by the N+1 lab (variants l and m) and the
 * models lab (legacy accessor/mutator/scope syntax, AsArrayObject).
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
     * Book casts meta with AsCollection; here the same JSON becomes an ArrayObject
     * (array syntax $book->meta['pages'], changes tracked as dirty).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [...parent::casts(), 'meta' => AsArrayObject::class];
    }

    // ---- Legacy syntax (Laravel <= 8 style, still supported) -----------------
    // Compare with Book::isbn()/isbnFormatted() and Author::fullName(), which use
    // a single Attribute-returning method per attribute.

    /**
     * Legacy accessor: $book->display_title ("get" + StudlyName + "Attribute").
     */
    public function getDisplayTitleAttribute(): string
    {
        return $this->title.($this->published_year ? " ({$this->published_year})" : '');
    }

    /**
     * Legacy mutator: writes $this->attributes directly. "  Тіні   забутих " => "Тіні забутих".
     */
    public function setTitleAttribute(string $value): void
    {
        $this->attributes['title'] = Str::squish($value);
    }

    /**
     * Legacy local scope: LabBook::cheaperThan(Money::fromUah(200)). The "scope" prefix
     * is dropped when calling it. Modern equivalent: #[Scope] on a protected method (Book).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCheaperThan(Builder $query, Money|int $price): Builder
    {
        return $query->where('price', '<', $price instanceof Money ? $price->cents : $price);
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
            ->map(fn ($author) => $author->full_name)
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
