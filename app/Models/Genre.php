<?php

namespace App\Models;

use Database\Factories\GenreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'slug', 'parent_id'])]
class Genre extends Model
{
    /** @use HasFactory<GenreFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Genre, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Genre::class, 'parent_id');
    }

    /**
     * @return HasMany<Genre, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Genre::class, 'parent_id');
    }

    /**
     * Books whose main genre (books.genre_id) is this genre.
     *
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * Books that list this genre as a secondary one (book_genre pivot).
     *
     * @return BelongsToMany<Book, $this>
     */
    public function secondaryBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }

    /**
     * genres -> books (books.genre_id) -> reviews (reviews.book_id).
     *
     * @return HasManyThrough<Review, Book, $this>
     */
    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(
            Review::class, // final model
            Book::class,   // intermediate model
            'genre_id',    // FK on books -> genres
            'book_id',     // FK on reviews -> books
        );
    }
}
