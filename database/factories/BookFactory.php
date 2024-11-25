<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $author = DB::table('authors')->select('id')->inRandomOrder()->first();
        $genre = DB::table('genres')->select('id')->inRandomOrder()->first();

        return [
            'author_id' => $author->id,
            'genre_id' => $genre->id,
            'title' => $this->faker->sentence(3),
            'isbn' => $this->faker->isbn13(),
            'description' => $this->faker->realText(200),
            'cover' => $this->faker->imageUrl(480, 640, 'abstract', true),
        ];
    }
}
