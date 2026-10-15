<?php

namespace Database\Factories;

use App\Enums\BookFormat;
use App\Models\Book;
use App\Models\Genre;
use Database\Factories\Concerns\GeneratesUniqueValues;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
        $title = rtrim(fake()->sentence(fake()->numberBetween(1, 5)), '.');
        $publishedAt = fake()->optional(0.85)->dateTimeBetween('-40 years', 'now');

        return [
            'title' => $title,
            'slug' => static::uniqueSlug($title),
            'isbn' => static::uniqueIsbn(),
            'price' => fake()->numberBetween(299, 9999), // cents: $2.99 .. $99.99
            'published_year' => $publishedAt?->format('Y'),
            'format' => fake()->randomElement(BookFormat::cases())->value,
            'genre_id' => Genre::factory(),
            'published_at' => $publishedAt,
            'meta' => [
                'pages' => fake()->numberBetween(80, 1200),
                'language' => fake()->randomElement(['en', 'en', 'en', 'de', 'fr', 'es', 'uk']),
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
}
