<?php

use App\Lab\ModelExamples;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Profile;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/**
 * @return array<string, int>
 */
function modelsLabRowCounts(): array
{
    $tables = ['users', 'books', 'reviews', 'stocks', 'stock_movements', 'profiles'];

    return collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
}

test('every example runs without errors and writes are rolled back', function () {
    config(['database.seed_scale' => 'small']);
    $this->seed(DatabaseSeeder::class);
    $before = modelsLabRowCounts();

    $results = (new ModelExamples)->run();

    expect($results)->toHaveCount(27);

    foreach ($results as $result) {
        expect($result['error'])->toBeNull("{$result['title']}: {$result['error']}");
    }

    expect(modelsLabRowCounts())->toBe($before);

    $this->get(route('lab.models'))
        ->assertOk()
        ->assertSee(__('lab.models.title'))
        ->assertSee('ApprovedScope')
        ->assertDontSee(__('lab.common.error'));
});

test('lab page runs a constant number of queries', function () {
    expectConstantQueries(
        function (int $n) {
            $warehouse = Warehouse::factory()->create();
            Genre::factory()->child()->create();

            Book::factory()->count($n)->published()->withAuthors()->withReviews(1)->create()
                ->each(fn (Book $book) => $book->warehouses()->attach($warehouse, ['quantity' => 5, 'reserved' => 1]));
            Profile::factory()->count($n)->create(['phone' => '+380501234567']);
        },
        fn () => $this->get(route('lab.models')),
    );
});
