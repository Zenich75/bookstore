<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Pool of Ukrainian review texts. Faker's realText() costs ~0.5 ms, which
     * would add minutes to the 200k-review seed, so bodies are drawn from a pool.
     *
     * @var list<string>|null
     */
    protected static ?array $bodies = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            // Skewed towards positive ratings, like real stores.
            'rating' => fake()->randomElement([1, 2, 3, 3, 4, 4, 4, 5, 5, 5]),
            'body' => fake()->randomElement(static::$bodies ??= array_map(
                fn () => fake()->realTextBetween(80, 300),
                range(1, 300),
            )),
            'is_approved' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
        ]);
    }
}
