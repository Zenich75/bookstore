<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genresList = [
            'Action/Adventure fiction',
            'Children’s fiction',
            'Classic fiction',
            'Contemporary fiction',
            'Fantasy',
            'Graphic novel',
            'Historical fiction',
            'Horror',
            'Literary fiction',
            'Mystery',
            'New adult',
            'Romance',
            'Satire',
            'Science fiction',
            'Short story',
            'Thriller',
            'Western',
            'Women’s fiction',
            'Young adult',
            'Art & photography',
            'Autobiography/Memoir',
            'Biography',
            'Essays',
            'Food & drink',
            'History',
            'How-To/Guides',
            'Humanities & social sciences',
            'Humor',
            'Parenting',
            'Philosophy',
            'Religion & spirituality',
            'Science & technology',
            'Self-help',
            'Travel',
            'True crime',
        ];

        foreach ($genresList as $genre) {
            Genre::create([
                'genre' => $genre,
            ]);
        }
    }
}
