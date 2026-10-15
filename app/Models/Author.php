<?php

namespace App\Models;

use App\Enums\AuthorRole;
use App\Enums\ImageType;
use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['first_name', 'last_name', 'slug', 'birth_date', 'country'])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    /**
     * All credits: as author, co-author or translator.
     *
     * @return BelongsToMany<Book, $this, Pivot, 'credit'>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->as('credit');
    }

    /**
     * @return BelongsToMany<Book, $this, Pivot, 'credit'>
     */
    public function translatedBooks(): BelongsToMany
    {
        return $this->books()->wherePivot('role', AuthorRole::Translator->value);
    }

    /**
     * @return MorphOne<Image, $this>
     */
    public function photo(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', ImageType::Photo);
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
