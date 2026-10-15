<?php

namespace Database\Seeders;

use App\Enums\AuthorRole;
use App\Enums\ImageType;
use App\Models\Book;
use Database\Seeders\Support\BulkInserts;
use Database\Seeders\Support\SeedScale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BookSeeder extends Seeder
{
    use BulkInserts;

    public function run(): void
    {
        $scale = SeedScale::current();
        $now = now();
        $genreIds = DB::table('genres')->pluck('id')->all();
        $authorIds = DB::table('authors')->pluck('id')->all();

        DB::transaction(function () use ($scale, $now, $genreIds) {
            $this->insertChunked('books', (function () use ($scale, $now, $genreIds) {
                for ($i = 0; $i < $scale->books; $i++) {
                    // Passing genre_id prevents raw() from creating a Genre via Genre::factory().
                    $row = Book::factory()->raw(['genre_id' => Arr::random($genreIds)]);
                    $row['meta'] = json_encode($row['meta']); // no casts with the Query Builder

                    yield $row + ['created_at' => $now, 'updated_at' => $now];
                }
            })());
        });

        $bookMorph = (new Book)->getMorphClass();

        // Pivots and covers, built per chunk of 1000 books.
        DB::table('books')->select(['id', 'slug', 'genre_id'])
            ->chunkById(1000, function ($books) use ($now, $genreIds, $authorIds, $bookMorph) {
                $authorBook = [];
                $bookGenre = [];
                $images = [];

                foreach ($books as $book) {
                    // 1-3 authors; the first one is the main author.
                    $authors = $this->pickRandom($authorIds, mt_rand(1, 3));
                    foreach ($authors as $order => $authorId) {
                        $authorBook[] = [
                            'author_id' => $authorId,
                            'book_id' => $book->id,
                            'role' => ($order === 0 ? AuthorRole::Author : AuthorRole::CoAuthor)->value,
                            'sort_order' => $order,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    // ~10% are translations.
                    if (mt_rand(1, 100) <= 10) {
                        $translator = Arr::first($this->pickRandom(array_diff($authorIds, $authors), 1));
                        if ($translator !== null) {
                            $authorBook[] = [
                                'author_id' => $translator,
                                'book_id' => $book->id,
                                'role' => AuthorRole::Translator->value,
                                'sort_order' => count($authors),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    // 0-2 secondary genres in addition to books.genre_id.
                    $extraGenres = array_diff($genreIds, [$book->genre_id]);
                    foreach ($this->pickRandom($extraGenres, mt_rand(0, 2)) as $genreId) {
                        $bookGenre[] = ['book_id' => $book->id, 'genre_id' => $genreId];
                    }

                    // Cover for ~70% of books (placeholder path, no file).
                    if (mt_rand(1, 100) <= 70) {
                        $images[] = [
                            'imageable_type' => $bookMorph,
                            'imageable_id' => $book->id,
                            'path' => "covers/{$book->slug}.jpg",
                            'type' => ImageType::Cover->value,
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::transaction(function () use ($authorBook, $bookGenre, $images) {
                    $this->insertChunked('author_book', $authorBook);
                    $this->insertChunked('book_genre', $bookGenre);
                    $this->insertChunked('images', $images);
                });
            });
    }
}
