<?php

use App\Lab\RelationExamples;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.seed_scale' => 'small']);
    $this->seed(DatabaseSeeder::class);
});

/**
 * @return array<string, int>
 */
function rowCounts(): array
{
    $tables = ['users', 'books', 'reviews', 'images', 'taggables', 'book_genre', 'stocks', 'author_book'];

    return collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
}

test('lab page renders', function () {
    $this->get('/lab/relations')
        ->assertOk()
        ->assertSee('Lab: Eloquent relationships')
        ->assertSee('hasOneThrough')
        ->assertDontSee('Error:');
});

test('every lab example runs without errors or lazy loading', function () {
    $results = (new RelationExamples)->run();

    expect($results)->not->toBeEmpty();

    foreach ($results as $result) {
        expect($result['error'])->toBeNull("{$result['title']}: {$result['error']}")
            ->and($result['skipped'])->toBeNull("{$result['title']} was skipped")
            ->and($result['queries'])->not->toBeEmpty();
    }
});

test('write examples leave row counts unchanged', function () {
    $before = rowCounts();

    $this->get('/lab/relations')->assertOk();

    expect(rowCounts())->toBe($before);
});

test('write examples actually changed data before rolling back', function () {
    $writes = collect((new RelationExamples)->run())->where('writes', true);

    expect($writes)->not->toBeEmpty()
        ->and($writes->firstWhere('type', 'attach')['result'])->toMatchArray(['tags_after' => $writes->firstWhere('type', 'attach')['result']['tags_before'] + 1]);
});
