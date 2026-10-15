<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Tag;
use Database\Seeders\Support\BulkInserts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    use BulkInserts;

    private const TAGS = [
        'Bestseller', 'New Release', 'Award Winner', 'Classic', 'Staff Pick', 'Debut',
        'Book Club', 'Series', 'Standalone', 'Illustrated', 'Signed Edition', 'Limited Edition',
        'Translated', 'Based on True Story', 'Adapted to Film', 'Short Read', 'Epic', 'Cozy',
        'Dark', 'Funny', 'Inspirational', 'Educational', 'Family', 'Adventure', 'Philosophy',
        'Nobel Laureate', 'Pulitzer Prize', 'Booker Prize', 'Indie Author', 'Local Author',
    ];

    public function run(): void
    {
        $now = now();

        DB::transaction(function () {
            foreach (self::TAGS as $name) {
                Tag::create(['name' => $name, 'slug' => Str::slug($name)]);
            }

            $tagIds = DB::table('tags')->pluck('id')->all();

            // ~30% of books and authors get 1-3 tags.
            foreach (['books' => new Book, 'authors' => new Author] as $table => $model) {
                $morphClass = $model->getMorphClass();

                $this->insertChunked('taggables', (function () use ($table, $morphClass, $tagIds) {
                    foreach (DB::table($table)->select('id')->lazyById(1000) as $row) {
                        if (mt_rand(1, 100) > 30) {
                            continue;
                        }

                        foreach ($this->pickRandom($tagIds, mt_rand(1, 3)) as $tagId) {
                            yield [
                                'tag_id' => $tagId,
                                'taggable_type' => $morphClass,
                                'taggable_id' => $row->id,
                            ];
                        }
                    }
                })());
            }
        });
    }
}
