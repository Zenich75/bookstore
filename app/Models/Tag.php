<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasSlug;

    protected function slugSource(): string
    {
        return $this->name;
    }

    /**
     * @return MorphToMany<Book, $this>
     */
    public function books(): MorphToMany
    {
        return $this->morphedByMany(Book::class, 'taggable');
    }

    /**
     * @return MorphToMany<Author, $this>
     */
    public function authors(): MorphToMany
    {
        return $this->morphedByMany(Author::class, 'taggable');
    }
}
