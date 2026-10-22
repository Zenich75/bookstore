<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedScale;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    // Mutes the event dispatcher for the whole seeding run: even Model::create()
    // fires no events, so HasSlug, StockObserver etc. do not run — seeders set
    // slugs and write stock movements themselves (see docs/06-attributes-casts.md).
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Volume is controlled by SEED_SCALE=small|full (see SeedScale).
     */
    public function run(): void
    {
        // The query log keeps every executed query in memory; with ~300k inserted
        // rows that is pure overhead.
        DB::disableQueryLog();

        $this->command?->info('Seed scale: '.SeedScale::current()->name);

        $this->call([
            GenreSeeder::class,
            WarehouseSeeder::class,
            UserSeeder::class,
            AuthorSeeder::class,
            BookSeeder::class,
            StockSeeder::class,
            TagSeeder::class,
            ReviewSeeder::class,
        ]);

        // Reviews were bulk-inserted (no events, no jobs): fill books.rating_avg now,
        // in this process, so the catalog's "by rating" sort works right after seeding.
        Artisan::call('books:recalculate-ratings', ['--sync' => true]);
        $this->command?->info(trim(Artisan::output()));
    }
}
