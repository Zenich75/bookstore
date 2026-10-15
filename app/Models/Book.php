<?php

namespace App\Models;

use App\Enums\AuthorRole;
use App\Enums\BookFormat;
use App\Enums\ImageType;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

// Laravel 13: mass-assignable attributes declared with a PHP attribute.
#[Fillable([
    'title',
    'slug',
    'isbn',
    'price',
    'published_year',
    'format',
    'genre_id',
    'published_at',
    'meta',
])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, SoftDeletes;

    // Legacy syntax (Laravel <= 12), equivalent to the #[Fillable] attribute above:
    // protected $fillable = [
    //     'title',
    //     'slug',
    //     'isbn',
    //     'price',
    //     'published_year',
    //     'format',
    //     'genre_id',
    //     'published_at',
    //     'meta',
    // ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => BookFormat::class,
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Main genre.
     *
     * @return BelongsTo<Genre, $this>
     */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    /**
     * Secondary genres (book_genre pivot, no extra columns).
     *
     * @return BelongsToMany<Genre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    /**
     * All credits (authors, co-authors, translators) in display order.
     *
     * @return BelongsToMany<Author, $this, Pivot, 'credit'>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->as('credit');
    }

    /**
     * @return BelongsToMany<Author, $this, Pivot, 'credit'>
     */
    public function translators(): BelongsToMany
    {
        return $this->authors()->wherePivot('role', AuthorRole::Translator->value);
    }

    /**
     * @return BelongsToMany<Warehouse, $this, Stock, 'stock'>
     */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'stocks')
            ->using(Stock::class)
            ->withPivot(['id', 'quantity', 'reserved', 'shelf_code'])
            ->withTimestamps()
            ->as('stock');
    }

    /**
     * @return HasMany<Stock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    /**
     * One row out of many: the newest review (MAX(id)).
     *
     * @return HasOne<Review, $this>
     */
    public function latestReview(): HasOne
    {
        return $this->hasOne(Review::class)->latestOfMany();
    }

    /**
     * One row out of many: the highest rating (ties broken by MAX(id)).
     *
     * @return HasOne<Review, $this>
     */
    public function bestReview(): HasOne
    {
        return $this->hasOne(Review::class)->ofMany('rating', 'max');
    }

    /**
     * @return MorphOne<Image, $this>
     */
    public function cover(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', ImageType::Cover);
    }

    /**
     * @return MorphMany<Image, $this>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
