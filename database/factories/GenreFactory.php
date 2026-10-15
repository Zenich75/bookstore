<?php

namespace Database\Factories;

use App\Models\Genre;
use Database\Factories\Concerns\GeneratesUniqueValues;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Genre>
 */
class GenreFactory extends Factory
{
    use GeneratesUniqueValues;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->words(2, true));

        return [
            'name' => $name,
            'slug' => static::uniqueSlug($name),
            'parent_id' => null,
        ];
    }

    /**
     * A sub-genre; creates a root parent unless one is given.
     */
    public function child(Genre|int|null $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent instanceof Genre ? $parent->getKey() : ($parent ?? Genre::factory()),
        ]);
    }
}
