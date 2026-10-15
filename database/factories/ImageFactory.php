<?php

namespace Database\Factories;

use App\Enums\ImageType;
use App\Models\Book;
use App\Models\Image;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Polymorphic parent: a Factory value fills imageable_id; the type is set explicitly.
            'imageable_type' => (new Book)->getMorphClass(),
            'imageable_id' => Book::factory(),
            'path' => 'covers/'.fake()->uuid().'.jpg', // placeholder, no real file
            'type' => ImageType::Cover->value,
            'sort_order' => 0,
        ];
    }
}
