<?php

use App\Http\Controllers\Lab\LabQueuesController;
use App\Lab\Queues\LabPingJob;
use App\Lab\Queues\MissingModelJob;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $book = Book::factory()->withAuthors(2)->inWarehouses(1)->create();
    Review::factory()->approved()->count(3)->for($book)->create();
});

test('every variant dispatches without errors (sync connection in tests)', function (string $variant) {
    $this->post(route('lab.queues.dispatch', $variant))->assertRedirect(route('lab.queues'));
})->with(array_merge(...array_values(LabQueuesController::GROUPS)));

test('the page shows the run log with worker-side measurements', function () {
    $this->post(route('lab.queues.dispatch', 'with_relations'));
    $this->post(route('lab.queues.dispatch', 'without_relations'));

    $runs = DB::table('lab_job_runs')->pluck('restore_queries', 'variant');

    // Book + genre, authors, reviews, reviews.user, stocks, tags re-queried vs. the book alone.
    expect($runs['with_relations'])->toBeGreaterThanOrEqual(6)
        ->and($runs['without_relations'])->toBe(1);

    $this->get(route('lab.queues'))->assertOk()->assertSee('data-run="with_relations"', false);
});

test('a collection of models: small payload, but every model is hydrated on the worker', function () {
    Book::factory()->count(20)->create();

    $this->post(route('lab.queues.dispatch', 'collection'))->assertSessionHas('lab_details');

    $details = session('lab_details');
    expect($details['payload_bytes'])->toBeLessThan($details['without_serializes_models_bytes'] / 5);
    expect(json_decode(DB::table('lab_job_runs')->where('variant', 'collection')->value('details'))->models)->toBe(Book::count());
});

test('afterCommit: a job dispatched in a rolled-back transaction is never queued', function () {
    config(['queue.default' => 'database']);

    try {
        DB::transaction(function () {
            LabPingJob::dispatch('rolled_back')->afterCommit();
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('jobs')->count())->toBe(0);

    DB::transaction(fn () => LabPingJob::dispatch('committed'));
    expect(DB::table('jobs')->count())->toBe(1);
});

test('DeleteWhenMissingModels: the job of a deleted model is discarded, not failed', function () {
    config(['queue.default' => 'database']);
    $book = Book::factory()->create(['genre_id' => Genre::factory()]);

    MissingModelJob::dispatch($book);
    $book->forceDelete();
    $this->artisan('queue:work', ['--once' => true])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('lab_job_runs')->where('variant', 'missing_model')->count())->toBe(0);
});
