<?php

namespace Database\Seeders;

use App\Enums\ImageType;
use App\Models\Author;
use Database\Seeders\Support\BulkInserts;
use Database\Seeders\Support\SeedScale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuthorSeeder extends Seeder
{
    use BulkInserts;

    public function run(): void
    {
        $scale = SeedScale::current();
        $now = now();

        DB::transaction(function () use ($scale, $now) {
            $this->insertChunked('authors', (function () use ($scale, $now) {
                for ($i = 0; $i < $scale->authors; $i++) {
                    yield Author::factory()->raw() + ['created_at' => $now, 'updated_at' => $now];
                }
            })());

            // Placeholder photo for ~50% of authors (paths only, no files).
            $morphClass = (new Author)->getMorphClass();

            $this->insertChunked('images', (function () use ($morphClass, $now) {
                foreach (DB::table('authors')->select(['id', 'slug'])->lazyById(1000) as $author) {
                    if (mt_rand(1, 100) <= 50) {
                        yield [
                            'imageable_type' => $morphClass,
                            'imageable_id' => $author->id,
                            'path' => "authors/{$author->slug}.jpg",
                            'type' => ImageType::Photo->value,
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            })());
        });
    }
}
