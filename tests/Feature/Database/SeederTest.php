<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

test('database seeder runs with the small scale', function () {
    config(['database.seed_scale' => 'small']);

    $this->seed(DatabaseSeeder::class);

    expect(DB::table('books')->count())->toBe(500)
        ->and(DB::table('authors')->count())->toBe(150)
        ->and(DB::table('users')->count())->toBe(100)
        ->and(DB::table('profiles')->count())->toBe(100)
        ->and(DB::table('warehouses')->count())->toBe(5)
        ->and(DB::table('tags')->count())->toBe(30)
        ->and(DB::table('genres')->whereNull('parent_id')->count())->toBeGreaterThanOrEqual(10)
        ->and(DB::table('reviews')->count())->toBeGreaterThan(1000)
        ->and(DB::table('stock_movements')->count())->toBe(DB::table('stocks')->count());

    $admin = User::where('email', 'admin@example.com')->first();
    expect($admin->role)->toBe(UserRole::Admin)
        ->and(User::where('role', UserRole::Manager)->whereNotNull('warehouse_id')->count())->toBe(5);

    // Every book has a main author.
    expect(DB::table('books')->whereNotExists(fn ($q) => $q->from('author_book')
        ->whereColumn('author_book.book_id', 'books.id')->where('role', 'author'))->count())->toBe(0);
});
