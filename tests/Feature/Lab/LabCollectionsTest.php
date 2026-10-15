<?php

use App\Lab\CollectionExamples;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Profile;
use App\Models\Review;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;

/**
 * The lab's per-model approaches are deliberate N+1s over fixed samples (20 / 200
 * books), so seed above those caps; $n then varies only data the lab aggregates.
 */
function seedCollectionsLab(int $n): void
{
    $genre = Genre::factory()->create();
    $books = Book::factory()->count(200 + $n)->published()->for($genre)->create();
    $warehouse = Warehouse::factory()->create();
    $books->take($n)->each(fn (Book $book) => $warehouse->books()->attach($book, ['quantity' => 5]));
    Review::factory()->count($n)->for($books->first())->create(); // unapproved, < one chunk of 100
    Profile::factory()->count(3)->create();
}

test('every lab section renders', function (string $section) {
    config(['database.seed_scale' => 'small']);
    $this->seed(DatabaseSeeder::class);

    $this->get(route('lab.collections', ['section' => $section]))
        ->assertOk()
        ->assertSee(CollectionExamples::sections()[$section])
        ->assertDontSee(__('lab.common.error').'</strong>', false);
})->with(CollectionExamples::SECTIONS);

test('unknown section is a 404', function () {
    $this->get(route('lab.collections', ['section' => 'nope']))->assertNotFound();
});

test('lab sections run a constant number of queries', function (string $section) {
    expectConstantQueries(
        fn (int $n) => seedCollectionsLab($n),
        fn () => $this->get(route('lab.collections', ['section' => $section])),
    );
})->with(CollectionExamples::SECTIONS);

test('chunk() skips rows when the callback changes the filtered column; chunkById() does not', function () {
    config(['database.seed_scale' => 'small']);
    $this->seed(DatabaseSeeder::class);
    $unapproved = Review::where('is_approved', false)->count();

    $examples = new CollectionExamples;
    [$chunk, $chunkById] = $examples->run($examples->comparisons('chunk-pitfall')[0]);

    expect($unapproved)->toBeGreaterThan(200)
        ->and($chunk['profile']->result['unapproved_before'])->toBe($unapproved)
        ->and($chunk['profile']->result['unapproved_after'])->toBeGreaterThan(0)
        ->and($chunkById['profile']->result['unapproved_after'])->toBe(0)
        // Both ran inside rolled-back transactions.
        ->and(Review::where('is_approved', false)->count())->toBe($unapproved);
});

test('toQuery()->update() only touches the models in the collection', function () {
    $books = Book::factory()->count(5)->create(['published_year' => 1990]);

    $subset = Book::whereIn('id', $books->take(2)->modelKeys())->get();
    $affected = $subset->toQuery()->update(['published_year' => 2000]);

    expect($affected)->toBe(2)
        ->and(Book::where('published_year', 2000)->pluck('id')->sort()->values()->all())
        ->toBe($subset->modelKeys())
        ->and(Book::where('published_year', 1990)->count())->toBe(3);
});

test('mass update comparison: Eloquent updates updated_at, DB::table() does not', function () {
    Book::factory()->count(10)->create(['updated_at' => now()->subDay()]);

    $examples = new CollectionExamples;
    $results = collect($examples->run($examples->comparisons('mass-update')[0]))
        ->mapWithKeys(fn ($run) => [$run['approach']->label => $run['profile']->result]);

    expect($results['$books->toQuery()->increment()'])->toBe(['rows' => 10, 'updated_at_changed' => 10])
        ->and($results['DB::table()->increment()'])->toBe(['rows' => 10, 'updated_at_changed' => 0])
        ->and($results['each->save()'])->toBe(['rows' => 10, 'updated_at_changed' => 10]);
});

test('comparing by key vs by value', function () {
    Book::factory()->count(20)->create();

    $examples = new CollectionExamples;
    [$eloquent, $base] = $examples->run($examples->comparisons('eloquent-collection')[4]);

    expect($eloquent['profile']->result)->toMatchArray(['unique' => 15, 'contains' => true])
        ->and($base['profile']->result)->toMatchArray(['unique' => 16])
        ->and($base['profile']->result['diff'])->toContain(Book::orderBy('id')->skip(5)->value('id'));
});
