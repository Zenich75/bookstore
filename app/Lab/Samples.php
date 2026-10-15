<?php

namespace App\Lab;

use App\Enums\UserRole;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Image;
use App\Models\Review;
use App\Models\StockMovement;
use App\Models\Tag;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Deterministic sample record ids for the lab examples ("first X that has Y").
 *
 * Resolved once, before query logging starts, so the SQL shown for an example
 * contains only what the example itself runs.
 */
final class Samples
{
    /**
     * @var array<string, int|null>
     */
    private array $ids = [];

    public function load(): self
    {
        $this->ids = [
            'reader' => User::where('role', UserRole::Reader)->has('profile')->has('reviews')->orderBy('id')->value('id'),
            'manager' => User::where('role', UserRole::Manager)->orderBy('id')->value('id'),
            'warehouse' => Warehouse::has('manager')->has('books')->orderBy('id')->value('id'),
            'book' => Book::has('reviews', '>=', 3)->has('cover')->has('tags')->has('genres')->orderBy('id')->value('id'),
            'otherBook' => Book::orderByDesc('id')->value('id'),
            'bookWithTranslator' => Book::has('translators')->orderBy('id')->value('id'),
            'genreWithChildren' => Genre::has('children')->orderBy('id')->value('id'),
            'translator' => Author::has('translatedBooks')->orderBy('id')->value('id'),
            'authorWithPhoto' => Author::has('photo')->orderBy('id')->value('id'),
            'review' => Review::orderBy('id')->value('id'),
            'authorImage' => Image::where('imageable_type', 'author')->orderBy('id')->value('id'),
            'tag' => Tag::has('books')->has('authors')->orderBy('id')->value('id'),
            'movement' => StockMovement::whereNotNull('user_id')->orderBy('id')->value('id'),
        ];

        return $this;
    }

    /**
     * @throws MissingSample when the database has no suitable record (e.g. not seeded).
     */
    public function id(string $key): int
    {
        return $this->ids[$key] ?? throw new MissingSample("No sample record for \"{$key}\" — run the seeders.");
    }
}
