<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedScale;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
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
    }
}
