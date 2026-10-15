<?php

namespace App\Models;

use App\Enums\BookFormat;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
