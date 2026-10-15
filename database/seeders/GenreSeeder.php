<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenreSeeder extends Seeder
{
    /**
     * Fixed two-level genre tree: root => children.
     *
     * @var array<string, list<string>>
     */
    private const TREE = [
        'Fiction' => ['Literary Fiction', 'Historical Fiction', 'Contemporary Fiction'],
        'Mystery & Thriller' => ['Crime', 'Detective', 'Psychological Thriller'],
        'Science Fiction' => ['Space Opera', 'Cyberpunk', 'Dystopian'],
        'Fantasy' => ['Epic Fantasy', 'Urban Fantasy', 'Dark Fantasy'],
        'Romance' => ['Romantic Comedy', 'Historical Romance', 'Paranormal Romance'],
        'Horror' => ['Gothic Horror', 'Supernatural Horror'],
        'Biography & Memoir' => ['Autobiography', 'Political Biography', 'Travel Memoir'],
        'History' => ['Ancient History', 'Military History', 'World History'],
        'Science & Technology' => ['Popular Science', 'Computer Science', 'Mathematics'],
        'Children\'s Books' => ['Picture Books', 'Young Adult', 'Middle Grade'],
        'Business & Economics' => ['Management', 'Personal Finance'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::TREE as $rootName => $children) {
                $root = Genre::create(['name' => $rootName, 'slug' => Str::slug($rootName)]);

                foreach ($children as $childName) {
                    Genre::create([
                        'name' => $childName,
                        'slug' => Str::slug($childName),
                        'parent_id' => $root->id,
                    ]);
                }
            }
        });
    }
}
