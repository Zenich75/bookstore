<?php

namespace Database\Factories;

use App\Enums\AuthorRole;
use App\Enums\BookFormat;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\Warehouse;
use Database\Factories\Concerns\GeneratesUniqueValues;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    use GeneratesUniqueValues;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Ukrainian text from Faker's uk_UA corpus (realText); trailing punctuation removed
        // with a /u regex (rtrim() is byte-based and would cut multibyte letters).
        $title = Str::ucfirst(preg_replace('/[\s\p{P}]+$/u', '', fake()->realTextBetween(12, 40)));
        $publishedAt = fake()->optional(0.85)->dateTimeBetween('-40 years', 'now');

        return [
            'title' => $title,
            'slug' => static::uniqueSlug($title),
            'isbn' => static::uniqueIsbn(),
            'price' => fake()->numberBetween(9900, 99900), // kopecks: 99 ₴ .. 999 ₴
            'published_year' => $publishedAt?->format('Y'),
            'format' => fake()->randomElement(BookFormat::cases())->value,
            'genre_id' => Genre::factory(),
            'published_at' => $publishedAt,
            'meta' => [
                'pages' => fake()->numberBetween(80, 1200),
                'language' => fake()->randomElement(['uk', 'uk', 'uk', 'en', 'de', 'fr', 'pl']),
                'publisher' => fake()->company(),
            ],
        ];
    }

    public function published(): static
    {
        return $this->state(function (array $attributes) {
            $publishedAt = Carbon::instance(fake()->dateTimeBetween('-40 years', '-1 day'));

            return [
                'published_at' => $publishedAt,
                'published_year' => $publishedAt->year,
            ];
        });
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
            'published_year' => null,
        ]);
    }

    public function format(BookFormat $format): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => $format->value,
        ]);
    }

    /**
     * Attach reviews via the reviews() hasMany relation.
     */
    public function withReviews(int $count = 3): static
    {
        return $this->has(Review::factory()->count($count), 'reviews');
    }

    /**
     * Attach authors via the authors() belongsToMany relation (pivot role + sort_order).
     */
    public function withAuthors(int $count = 1): static
    {
        return $this->hasAttached(
            Author::factory()->count($count),
            // Called once per attached author: the first becomes the main author.
            function (Book $book) {
                $position = $book->authors()->count();

                return [
                    'role' => ($position === 0 ? AuthorRole::Author : AuthorRole::CoAuthor)->value,
                    'sort_order' => $position,
                ];
            },
            'authors',
        );
    }

    /**
     * Stock the book in new warehouses via the warehouses() relation (Stock pivot).
     */
    public function inWarehouses(int $count = 1): static
    {
        return $this->hasAttached(
            Warehouse::factory()->count($count),
            fn () => [
                'quantity' => fake()->numberBetween(0, 100),
                'reserved' => 0,
                'shelf_code' => fake()->bothify('?-##-#'),
            ],
            'warehouses',
        );
    }
}
