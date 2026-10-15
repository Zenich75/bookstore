<?php

namespace Database\Factories;

use App\Models\Author;
use Database\Factories\Concerns\GeneratesUniqueValues;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    use GeneratesUniqueValues;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'slug' => static::uniqueSlug($firstName.' '.$lastName),
            'birth_date' => fake()->optional(0.8)->dateTimeBetween('-90 years', '-20 years')?->format('Y-m-d'),
            'country' => fake()->optional(0.9)->countryCode(),
        ];
    }
}
